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

func main() {
	if BOT_TOKEN == "" || API_BASE_URL == "" {
		log.Fatal("Missing BOT_TOKEN or API_BASE_URL environment variables")
	}

	port := os.Getenv("PORT")
	if port == "" {
		port = "8080"
	}

	// HTTP handler for health check or simple response
	http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte("Telegram bot is running"))
	})

	// Run Telegram polling loop concurrently
	go runBotLoop()

	log.Printf("Starting server on port %s", port)
	err := http.ListenAndServe(":"+port, nil)
	if err != nil {
		log.Fatalf("Server error: %v", err)
	}
}

// runBotLoop polls Telegram updates every 5 seconds
func runBotLoop() {
	var lastUpdateID int

	for {
		apiURL := fmt.Sprintf("https://api.telegram.org/bot%s/getUpdates?offset=%d", BOT_TOKEN, lastUpdateID+1)

		resp, err := http.Get(apiURL)
		if err != nil {
			log.Printf("Failed to connect to Telegram API: %v", err)
			time.Sleep(5 * time.Second)
			continue
		}

		body, err := io.ReadAll(resp.Body)
		resp.Body.Close()
		if err != nil {
			log.Printf("Failed to read Telegram response: %v", err)
			time.Sleep(5 * time.Second)
			continue
		}

		var telegramResp TelegramResponse
		err = json.Unmarshal(body, &telegramResp)
		if err != nil {
			log.Printf("Failed to parse Telegram JSON: %v", err)
			time.Sleep(5 * time.Second)
			continue
		}

		if !telegramResp.OK {
			log.Printf("Telegram API returned error: %s", string(body))
			time.Sleep(5 * time.Second)
			continue
		}

		if len(telegramResp.Result) == 0 {
			// No new updates
			time.Sleep(5 * time.Second)
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
		time.Sleep(5 * time.Second)
	}
}

func processMessage(chatID int64, message string) {
	if isValidEmail(message) {
		exists, err := checkEmailExists(message)
		if err != nil {
			log.Printf("Error checking email existence: %v", err)
			sendTelegramMessage(BOT_TOKEN, chatID, "⚠ Server error while checking email.")
			return
		}

		if !exists {
			sendTelegramMessage(BOT_TOKEN, chatID, "❌ Not an employee of Zorqnet Technology.")
			return
		}

		success, err := updateChatID(message, chatID)
		if err != nil {
			log.Printf("Error updating chat ID: %v", err)
			sendTelegramMessage(BOT_TOKEN, chatID, "⚠ Server error while saving chat ID.")
			return
		}

		if success {
			sendTelegramMessage(BOT_TOKEN, chatID, "✅ Chat registration success!")
		} else {
			sendTelegramMessage(BOT_TOKEN, chatID, "⚠ Database error while saving chat ID.")
		}

	} else {
		sendTelegramMessage(BOT_TOKEN, chatID, "📩 Please send a valid email address to register.")
	}
}

// checkEmailExists calls your PHP API to verify email
func checkEmailExists(email string) (bool, error) {
	url := fmt.Sprintf("%s/check-email/%s", API_BASE_URL, url.PathEscape(email))

	resp, err := http.Get(url)
	if err != nil {
		return false, err
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return false, err
	}

	var emailResp EmailCheckResponse
	if err := json.Unmarshal(body, &emailResp); err != nil {
		return false, err
	}

	return emailResp.Exists, nil
}

// updateChatID calls your PHP API to update chat ID
func updateChatID(email string, chatID int64) (bool, error) {
	url := fmt.Sprintf("%s/update-chat-id", API_BASE_URL)

	reqData := UpdateChatIDRequest{
		Email:  email,
		ChatID: chatID,
	}

	jsonData, err := json.Marshal(reqData)
	if err != nil {
		return false, err
	}

	resp, err := http.Post(url, "application/json", bytes.NewBuffer(jsonData))
	if err != nil {
		return false, err
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return false, err
	}

	var updateResp UpdateChatIDResponse
	if err := json.Unmarshal(body, &updateResp); err != nil {
		return false, err
	}

	return updateResp.Success, nil
}

// isValidEmail uses regex to validate email addresses
func isValidEmail(email string) bool {
	emailRegex := regexp.MustCompile(`^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$`)
	return emailRegex.MatchString(email)
}

// sendTelegramMessage sends text to a Telegram chat
func sendTelegramMessage(token string, chatID int64, text string) {
	sendURL := fmt.Sprintf("https://api.telegram.org/bot%s/sendMessage", token)

	data := url.Values{}
	data.Set("chat_id", fmt.Sprintf("%d", chatID))
	data.Set("text", text)

	resp, err := http.PostForm(sendURL, data)
	if err != nil {
		log.Printf("Failed to send Telegram message: %v", err)
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		log.Printf("Telegram API returned status: %d", resp.StatusCode)
	}
}
