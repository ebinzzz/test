package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"net/url"
	"regexp"
	"strings"
)

// Configuration
const (
	BOT_TOKEN = "8441678945:AAFmwSXzkBErmzQLmXkwzwtaDmIIvF05nP0"
	API_BASE_URL = "http://www.zadmin.free.nf/api.php" // Replace with your InfinityFree domain
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
	// Telegram API endpoint
	apiURL := fmt.Sprintf("https://api.telegram.org/bot%s/getUpdates", BOT_TOKEN)

	// Get updates
	resp, err := http.Get(apiURL)
	if err != nil {
		log.Fatalf("Failed to connect to Telegram API: %v", err)
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		log.Fatalf("Failed to read response: %v", err)
	}

	var telegramResp TelegramResponse
	if err := json.Unmarshal(body, &telegramResp); err != nil {
		log.Fatalf("Failed to parse JSON: %v", err)
	}

	if !telegramResp.OK {
		log.Fatalf("Telegram API returned error: %s", string(body))
	}

	if len(telegramResp.Result) == 0 {
		log.Fatal("No messages found. Send an email address to the bot, then run this script.")
	}

	var lastUpdateID int
	latestMessages := make(map[int64]MessageData) // store last message per chat_id

	// Step 1: Gather only last message for each chat_id
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

	// Step 2: Process only the last message per chat
	for _, msgData := range latestMessages {
		chatID := msgData.ChatID
		message := msgData.Message

		if isValidEmail(message) {
			// Check if email exists via API
			exists, err := checkEmailExists(message)
			if err != nil {
				log.Printf("Error checking email: %v", err)
				sendTelegramMessage(BOT_TOKEN, chatID, "⚠ Server error while checking email.")
				continue
			}

			if !exists {
				sendTelegramMessage(BOT_TOKEN, chatID, "❌ Not an employee of Zorqnet Technology.")
			} else {
				// Update chat ID via API
				success, err := updateChatID(message, chatID)
				if err != nil {
					log.Printf("Error updating chat ID: %v", err)
					sendTelegramMessage(BOT_TOKEN, chatID, "⚠ Server error while saving chat ID.")
				} else if success {
					sendTelegramMessage(BOT_TOKEN, chatID, "✅ Chat registration success!")
				} else {
					sendTelegramMessage(BOT_TOKEN, chatID, "⚠ Database error while saving chat ID.")
				}
			}
		} else {
			sendTelegramMessage(BOT_TOKEN, chatID, "📩 Please send a valid email address to register.")
		}
	}

	// Step 3: Mark all updates as processed
	if lastUpdateID != 0 {
		offsetURL := fmt.Sprintf("%s?offset=%d", apiURL, lastUpdateID+1)
		http.Get(offsetURL) // We don't need to handle the response
	}
}

// checkEmailExists calls the PHP API to check if email exists
func checkEmailExists(email string) (bool, error) {
	url := fmt.Sprintf("%s/check-email/%s", API_BASE_URL, url.QueryEscape(email))
	
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

// updateChatID calls the PHP API to update chat ID
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

// isValidEmail checks if the given string is a valid email address
func isValidEmail(email string) bool {
	emailRegex := regexp.MustCompile(`^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$`)
	return emailRegex.MatchString(email)
}

// sendTelegramMessage sends a message to a Telegram chat
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

	// Optional: Check response status
	if resp.StatusCode != http.StatusOK {
		log.Printf("Telegram API returned status: %d", resp.StatusCode)
	}
}