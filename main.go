package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"net/url"
	"os"
	"regexp"
	"strings"
	"time"
)

// Load config from env vars
var (
	BOT_TOKEN    = os.Getenv("BOT_TOKEN")
	API_BASE_URL = os.Getenv("API_BASE_URL")
)

// Configuration constants
const (
	MAX_RETRIES = 3
	RETRY_DELAY = time.Second
	POLL_INTERVAL = 5 * time.Second
	HTTP_TIMEOUT = 30 * time.Second
)

// TelegramUpdate represents the structure of Telegram API updates
type TelegramUpdate struct {
	UpdateID int `json:"update_id"`
	Message  *struct {
		Chat struct {
			ID int64 `json:"id"`
		} `json:"chat"`
		Text string `json:"text"`
	} `json:"message"`
}

// TelegramResponse represents the API response structure
type TelegramResponse struct {
	OK     bool             `json:"ok"`
	Result []TelegramUpdate `json:"result"`
}

// API Response structures
type EmailCheckResponse struct {
	Exists bool   `json:"exists"`
	Email  string `json:"email"`
}

type UpdateChatIDRequest struct {
	Email  string `json:"email"`
	ChatID int64  `json:"chat_id"`
}

type UpdateChatIDResponse struct {
	Success bool   `json:"success"`
	Message string `json:"message"`
	Error   string `json:"error"`
}

// MessageData stores processed message information
type MessageData struct {
	UpdateID int
	ChatID   int64
	Message  string
}

// Create HTTP client with timeout
var httpClient = &http.Client{
	Timeout: HTTP_TIMEOUT,
}

func main() {
	if BOT_TOKEN == "" || API_BASE_URL == "" {
		log.Fatal("Missing BOT_TOKEN or API_BASE_URL environment variables")
	}

	port := os.Getenv("PORT")
	if port == "" {
		port = "8080"
	}

	log.Printf("Starting with BOT_TOKEN: %s...", BOT_TOKEN[:10])
	log.Printf("Starting with API_BASE_URL: %s", API_BASE_URL)

	// HTTP handlers
	http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("Telegram bot is running"))
	})

	// Health check endpoint
	http.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("OK"))
	})

	// Start HTTP server in a goroutine
	server := &http.Server{Addr: ":" + port}
	
	go func() {
		log.Printf("Starting HTTP server on port %s", port)
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("HTTP server error: %v", err)
		}
	}()

	// Give the server a moment to start
	time.Sleep(100 * time.Millisecond)
	log.Printf("HTTP server started successfully on port %s", port)

	// Start Telegram bot loop
	log.Println("Starting Telegram bot polling...")
	runBotLoop()
}

// runBotLoop polls Telegram updates with better error handling
func runBotLoop() {
	var lastUpdateID int
	consecutiveErrors := 0
	log.Println("Bot polling loop started")
	
	// Add a unique identifier for this instance
	instanceID := fmt.Sprintf("bot-%d", time.Now().Unix())
	log.Printf("Bot instance ID: %s", instanceID)

	for {
		apiURL := fmt.Sprintf("https://api.telegram.org/bot%s/getUpdates?offset=%d", BOT_TOKEN, lastUpdateID+1)

		resp, err := httpClient.Get(apiURL)
		if err != nil {
			consecutiveErrors++
			log.Printf("Failed to connect to Telegram API (error %d): %v", consecutiveErrors, err)
			
			// Exponential backoff for consecutive errors
			backoffDuration := time.Duration(consecutiveErrors) * POLL_INTERVAL
			if backoffDuration > 60*time.Second {
				backoffDuration = 60*time.Second
			}
			time.Sleep(backoffDuration)
			continue
		}

		body, err := io.ReadAll(resp.Body)
		resp.Body.Close()
		if err != nil {
			consecutiveErrors++
			log.Printf("Failed to read Telegram response: %v", err)
			time.Sleep(POLL_INTERVAL)
			continue
		}

		var telegramResp TelegramResponse
		err = json.Unmarshal(body, &telegramResp)
		if err != nil {
			consecutiveErrors++
			log.Printf("Failed to parse Telegram JSON: %v", err)
			time.Sleep(POLL_INTERVAL)
			continue
		}

		if !telegramResp.OK {
			consecutiveErrors++
			
			// Handle the specific conflict error
			if strings.Contains(string(body), "terminated by other getUpdates request") {
				log.Printf("CRITICAL: Multiple bot instances detected! Another instance is already running.")
				log.Printf("This instance (%s) will shut down to prevent conflicts.", instanceID)
				log.Fatal("Shutting down due to multiple instance conflict")
			}
			
			log.Printf("Telegram API returned error: %s", string(body))
			time.Sleep(POLL_INTERVAL)
			continue
		}

		// Reset error counter on success
		consecutiveErrors = 0

		if len(telegramResp.Result) == 0 {
			// No new updates
			time.Sleep(POLL_INTERVAL)
			continue
		}

		latestMessages := make(map[int64]MessageData) // store last message per chat_id

		for _, update := range telegramResp.Result {
			lastUpdateID = update.UpdateID
			if update.Message != nil {
				chatID := update.Message.Chat.ID
				message := strings.TrimSpace(update.Message.Text)
				latestMessages[chatID] = MessageData{
					UpdateID: update.UpdateID,
					ChatID:   chatID,
					Message:  message,
				}
			}
		}

		for _, msgData := range latestMessages {
			processMessage(msgData.ChatID, msgData.Message)
		}

		// Sleep before next poll
		time.Sleep(POLL_INTERVAL)
	}
}

func processMessage(chatID int64, message string) {
	log.Printf("Processing message from chat %d: %s", chatID, message)
	
	if !isValidEmail(message) {
		log.Printf("Invalid email format: %s", message)
		sendTelegramMessage(BOT_TOKEN, chatID, "📩 Please send a valid email address to register.")
		return
	}

	log.Printf("Email format is valid: %s", message)
	
	// Check if email exists with retry logic
	exists, err := checkEmailExistsWithRetry(message)
	if err != nil {
		log.Printf("Error checking email existence after retries: %v", err)
		sendTelegramMessage(BOT_TOKEN, chatID, "⚠ Server temporarily unavailable. Please try again later.")
		return
	}

	if !exists {
		log.Printf("Email not found in system: %s", message)
		sendTelegramMessage(BOT_TOKEN, chatID, "❌ Not an employee of Zorqnet Technology.")
		return
	}

	log.Printf("Email exists, updating chat ID for: %s", message)
	
	// Update chat ID with retry logic
	success, err := updateChatIDWithRetry(message, chatID)
	if err != nil {
		log.Printf("Error updating chat ID after retries: %v", err)
		sendTelegramMessage(BOT_TOKEN, chatID, "⚠ Server temporarily unavailable. Please try again later.")
		return
	}

	if success {
		log.Printf("Successfully registered chat ID for: %s", message)
		sendTelegramMessage(BOT_TOKEN, chatID, "✅ Chat registration success!")
	} else {
		log.Printf("Failed to save chat ID for: %s", message)
		sendTelegramMessage(BOT_TOKEN, chatID, "⚠ Database error while saving chat ID.")
	}
}

// checkEmailExistsWithRetry implements retry logic for email checking
func checkEmailExistsWithRetry(email string) (bool, error) {
	for attempt := 1; attempt <= MAX_RETRIES; attempt++ {
		exists, err := checkEmailExists(email)
		if err == nil {
			return exists, nil
		}

		log.Printf("Email check attempt %d/%d failed: %v", attempt, MAX_RETRIES, err)
		
		if attempt < MAX_RETRIES {
			// Exponential backoff: 1s, 2s, 4s
			backoffDuration := time.Duration(1<<uint(attempt-1)) * RETRY_DELAY
			log.Printf("Retrying in %v...", backoffDuration)
			time.Sleep(backoffDuration)
		}
	}
	
	return false, fmt.Errorf("failed after %d attempts", MAX_RETRIES)
}

// updateChatIDWithRetry implements retry logic for chat ID updates
func updateChatIDWithRetry(email string, chatID int64) (bool, error) {
	for attempt := 1; attempt <= MAX_RETRIES; attempt++ {
		success, err := updateChatID(email, chatID)
		if err == nil {
			return success, nil
		}

		log.Printf("Chat ID update attempt %d/%d failed: %v", attempt, MAX_RETRIES, err)
		
		if attempt < MAX_RETRIES {
			// Exponential backoff: 1s, 2s, 4s
			backoffDuration := time.Duration(1<<uint(attempt-1)) * RETRY_DELAY
			log.Printf("Retrying in %v...", backoffDuration)
			time.Sleep(backoffDuration)
		}
	}
	
	return false, fmt.Errorf("failed after %d attempts", MAX_RETRIES)
}

// checkEmailExists calls your PHP API to verify email
func checkEmailExists(email string) (bool, error) {
	// Build URL with proper query parameters
	baseURL := API_BASE_URL
	params := url.Values{}
	params.Set("action", "check_email")
	params.Set("email", email)
	
	apiURL := fmt.Sprintf("%s?%s", baseURL, params.Encode())
	log.Printf("Checking email API URL: %s", apiURL)

	// Create request with proper headers
	req, err := http.NewRequest("GET", apiURL, nil)
	if err != nil {
		return false, fmt.Errorf("failed to create request: %w", err)
	}
	
	// Add headers to mimic a real browser
	req.Header.Set("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36")
	req.Header.Set("Accept", "application/json, text/plain, */*")
	req.Header.Set("Accept-Language", "en-US,en;q=0.9")

	resp, err := httpClient.Do(req)
	if err != nil {
		return false, fmt.Errorf("HTTP request failed: %w", err)
	}
	defer resp.Body.Close()

	log.Printf("API response status: %d", resp.StatusCode)

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return false, fmt.Errorf("failed to read response body: %w", err)
	}

	// Check if response looks like HTML (error page)
	trimmedBody := strings.TrimSpace(string(body))
	if strings.HasPrefix(trimmedBody, "<") {
		log.Printf("API returned HTML instead of JSON: %s", trimmedBody[:min(200, len(trimmedBody))])
		return false, fmt.Errorf("API returned HTML error page (status: %d)", resp.StatusCode)
	}

	if resp.StatusCode != http.StatusOK {
		return false, fmt.Errorf("API returned status %d: %s", resp.StatusCode, string(body))
	}

	var emailResp EmailCheckResponse
	if err := json.Unmarshal(body, &emailResp); err != nil {
		log.Printf("JSON unmarshal failed: %v", err)
		return false, fmt.Errorf("invalid JSON response: %w", err)
	}

	log.Printf("Parsed response - Exists: %t, Email: %s", emailResp.Exists, emailResp.Email)
	return emailResp.Exists, nil
}

// updateChatID calls your PHP API to update chat ID
func updateChatID(email string, chatID int64) (bool, error) {
	apiURL := fmt.Sprintf("%s?action=update_chat_id", API_BASE_URL)

	reqData := UpdateChatIDRequest{
		Email:  email,
		ChatID: chatID,
	}

	jsonData, err := json.Marshal(reqData)
	if err != nil {
		return false, fmt.Errorf("JSON marshal failed: %w", err)
	}

	log.Printf("Updating chat ID URL: %s", apiURL)
	log.Printf("POST JSON data: %s", string(jsonData))

	// Create request with proper headers
	req, err := http.NewRequest("POST", apiURL, bytes.NewBuffer(jsonData))
	if err != nil {
		return false, fmt.Errorf("failed to create request: %w", err)
	}
	
	// Add headers to mimic a real browser
	req.Header.Set("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36")
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json, text/plain, */*")
	req.Header.Set("Accept-Language", "en-US,en;q=0.9")

	resp, err := httpClient.Do(req)
	if err != nil {
		return false, fmt.Errorf("HTTP POST failed: %w", err)
	}
	defer resp.Body.Close()

	log.Printf("Update API response status: %d", resp.StatusCode)

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return false, fmt.Errorf("failed to read update response: %w", err)
	}

	// Check if response looks like HTML
	trimmedBody := strings.TrimSpace(string(body))
	if strings.HasPrefix(trimmedBody, "<") {
		log.Printf("Update API returned HTML: %s", trimmedBody[:min(200, len(trimmedBody))])
		return false, fmt.Errorf("update API returned HTML error page (status: %d)", resp.StatusCode)
	}

	if resp.StatusCode != http.StatusOK {
		return false, fmt.Errorf("API returned status %d: %s", resp.StatusCode, string(body))
	}

	var updateResp UpdateChatIDResponse
	if err := json.Unmarshal(body, &updateResp); err != nil {
		return false, fmt.Errorf("invalid JSON response: %w", err)
	}

	log.Printf("Parsed update response - Success: %t", updateResp.Success)
	return updateResp.Success, nil
}

// isValidEmail uses regex to validate email addresses
func isValidEmail(email string) bool {
	emailRegex := regexp.MustCompile(`^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$`)
	return emailRegex.MatchString(email)
}

// sendTelegramMessage sends text to a Telegram chat with retry logic
func sendTelegramMessage(token string, chatID int64, text string) {
	for attempt := 1; attempt <= 2; attempt++ { // Only 2 attempts for messages
		if sendTelegramMessageOnce(token, chatID, text) {
			return // Success
		}
		
		if attempt < 2 {
			time.Sleep(time.Second)
		}
	}
	
	log.Printf("Failed to send message after 2 attempts to chat %d", chatID)
}

// sendTelegramMessageOnce sends a single message attempt
func sendTelegramMessageOnce(token string, chatID int64, text string) bool {
	sendURL := fmt.Sprintf("https://api.telegram.org/bot%s/sendMessage", token)

	data := url.Values{}
	data.Set("chat_id", fmt.Sprintf("%d", chatID))
	data.Set("text", text)

	resp, err := httpClient.PostForm(sendURL, data)
	if err != nil {
		log.Printf("Failed to send Telegram message: %v", err)
		return false
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		log.Printf("Telegram API returned status: %d", resp.StatusCode)
		return false
	}
	
	return true
}

// Helper function to get minimum of two integers
func min(a, b int) int {
	if a < b {
		return a
	}
	return b
}