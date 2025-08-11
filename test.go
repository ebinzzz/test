package main

import (
	"bytes"
	"database/sql"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"net/url"
	"regexp"
	"strings"

	_ "github.com/go-sql-driver/mysql"
)

// Configuration
const (
	BOT_TOKEN = "8441678945:AAFmwSXzkBErmzQLmXkwzwtaDmIIvF05nP0"
	DB_DSN    = "if0_39673757:eQtcVp3ouK@tcp(sql111.infinityfree.com:3306)/if0_39673757_admin"
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

// MessageData stores processed message information
type MessageData struct {
	UpdateID int
	ChatID   int64
	Message  string
}

func main() {
	// Connect to Database
	db, err := sql.Open("mysql", DB_DSN)
	if err != nil {
		log.Fatalf("Database connection failed: %v", err)
	}
	defer db.Close()

	// Test database connection
	if err := db.Ping(); err != nil {
		log.Fatalf("Database connection failed: %v", err)
	}

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
			// Check if email exists
			var id int
			query := "SELECT id FROM team_members WHERE email = ? LIMIT 1"
			err := db.QueryRow(query, message).Scan(&id)

			if err == sql.ErrNoRows {
				// Email not found
				sendTelegramMessage(BOT_TOKEN, chatID, "❌ Not an employee of Zorqnet Technology.")
			} else if err != nil {
				// Database error
				log.Printf("Database query error: %v", err)
				sendTelegramMessage(BOT_TOKEN, chatID, "⚠ Database error while checking email.")
			} else {
				// Email found, update chat_id
				updateQuery := "UPDATE team_members SET chat_id = ? WHERE email = ?"
				_, err := db.Exec(updateQuery, chatID, message)
				if err != nil {
					log.Printf("Database update error: %v", err)
					sendTelegramMessage(BOT_TOKEN, chatID, "⚠ Database error while saving chat ID.")
				} else {
					sendTelegramMessage(BOT_TOKEN, chatID, "✅ Chat registration success!")
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
