# SKILLHIVE MESSAGING SYSTEM - COMPLETE GUIDE

## Overview
The messaging system enables direct one-to-one communication between students, employers, and advisers. All messages are persisted in the database and synchronized in real-time.

---

## ARCHITECTURE

### Database Tables
1. **direct_message** - Stores all messages
2. **messaging_presence** - Tracks user online status

### Files
- **Backend API**: `/pages/common/messaging_api.php`
- **UI Page**: `/pages/student/messaging/index.php`
- **Config**: Uses existing `/backend/db_connect.php`

---

## API ENDPOINTS

### 1. LIST CONVERSATIONS
**Action**: `list_conversations`
**Method**: GET/POST
**Parameters**: None

**Response**:
```json
{
  "ok": true,
  "conversations": [
    {
      "conversation_id": "123_employer",
      "other_user_id": 123,
      "other_user_role": "employer",
      "other_user_name": "Tech Corp Inc",
      "last_message": "Great, see you next week!",
      "last_message_at": "2026-03-29 14:30:00",
      "last_message_time": "2m ago",
      "unread_count": 0
    }
  ],
  "total": 5
}
```

---

### 2. GET CONTACTS
**Action**: `get_contacts`
**Method**: GET/POST
**Parameters**: None

**Response**:
```json
{
  "ok": true,
  "contacts": [
    {
      "user_id": 123,
      "user_role": "employer",
      "name": "Tech Corp Inc",
      "role_label": "Employer"
    },
    {
      "user_id": 45,
      "user_role": "adviser",
      "name": "Dr. Maria Santos",
      "role_label": "Adviser"
    }
  ],
  "total": 12
}
```

**Contact Sources**:
- Employers from accepted internship applications
- Advisers assigned to student
- Previous message conversation contacts

---

### 3. GET CONVERSATION
**Action**: `get_conversation`
**Method**: GET
**Parameters**:
- `other_user_id` (int) - ID of the other user
- `other_user_role` (string) - Role of other user (student|employer|adviser)

**Response**:
```json
{
  "ok": true,
  "other_user_id": 123,
  "other_user_role": "employer",
  "other_user_name": "Tech Corp Inc",
  "messages": [
    {
      "message_id": 1001,
      "sender_id": 789,
      "sender_role": "student",
      "receiver_id": 123,
      "receiver_role": "employer",
      "message_text": "Hi, I'm interested in the internship",
      "is_read": true,
      "created_at": "2026-03-28 10:15:00",
      "time_label": "1d ago"
    }
  ],
  "total": 15
}
```

**Side Effects**:
- Automatically marks all unread messages from other user as read
- Updates user's last_seen timestamp

---

### 4. SEND MESSAGE
**Action**: `send_message`
**Method**: POST
**Parameters**:
- `receiver_id` (int) - ID of message recipient
- `receiver_role` (string) - Role of recipient
- `message` (string) - Message content (max 5000 chars)

**Response**:
```json
{
  "ok": true,
  "message_id": 1234,
  "created_at": "2026-03-29 15:45:23"
}
```

**Validation**:
- Message must not be empty
- Message limited to 5000 characters
- Receiver must exist in database

---

### 5. MARK AS READ
**Action**: `mark_as_read`
**Method**: POST
**Parameters**:
- `message_id` (int) - ID of message to mark read

**Response**:
```json
{
  "ok": true
}
```

---

### 6. GET UNREAD COUNT
**Action**: `get_unread_count`
**Method**: GET
**Parameters**: None

**Response**:
```json
{
  "ok": true,
  "unread_count": 3
}
```

---

### 7. UPDATE PRESENCE
**Action**: `update_presence`
**Method**: POST
**Parameters**: None

**Response**:
```json
{
  "ok": true
}
```

**Side Effects**:
- Updates/inserts user record in messaging_presence table with current timestamp

---

### 8. GET ONLINE STATUS
**Action**: `get_online_status`
**Method**: GET
**Parameters**:
- `other_user_id` (int) - User ID
- `other_user_role` (string) - User role

**Response**:
```json
{
  "ok": true,
  "is_online": true,
  "last_seen": "Just now"
}
```

**Logic**:
- User is considered "online" if last_seen timestamp is within 5 minutes
- Otherwise shows "Offline" with last_seen time

---

## SECURITY FEATURES

### Input Validation
- Role validation (must be: student, employer, adviser)
- User ID validation (must be positive integer)
- Message sanitization (trim, max 5000 chars)
- HTML escaping on frontend

### Database Safety
- Prepared statements for all queries
- No SQL injection vectors
- Role-based access control

### Error Handling
- Comprehensive try-catch blocks
- No sensitive error details exposed
- Graceful degradation

---

## FRONTEND BEHAVIOR

### Conversation List Tab
1. Shows all active conversations
2. Sorted by most recent message
3. Displays unread count badges
4. Click to open conversation

### Contacts Tab
1. Shows available people to message
2. Organized by role (Employer/Adviser)
3. Click to start new conversation
4. Auto-switches to Conversations tab

### Message Thread
1. Displays full conversation history (last 50 messages)
2. Own messages right-aligned, blue
3. Other messages left-aligned, gray
4. Timestamps on each message
5. Auto-marks as read when opened
6. Online status indicator

### Message Sending
- Real-time input
- Enter key to send (Shift+Enter for newline)
- Loading state on button
- Auto-refresh after send

### Search
- Works on active tab (Conversations or Contacts)
- Searches by user name and last message
- Real-time filtering

---

## DATA STRUCTURES

### Message Object
```javascript
{
  message_id: 1001,
  sender_id: 789,
  sender_role: "student",
  receiver_id: 123,
  receiver_role: "employer",
  message_text: "Hello there",
  is_read: true,
  created_at: "2026-03-29 10:15:00",
  time_label: "2m ago"
}
```

### Conversation Object
```javascript
{
  conversation_id: "123_employer",
  other_user_id: 123,
  other_user_role: "employer",
  other_user_name: "Tech Corp",
  last_message: "See you soon",
  last_message_at: "2026-03-29 14:30:00",
  last_message_time: "2m ago",
  unread_count: 0
}
```

### Contact Object
```javascript
{
  user_id: 123,
  user_role: "employer",
  name: "Tech Corp Inc",
  role_label: "Employer"
}
```

---

## WORKFLOW EXAMPLES

### Student Sending First Message to Employer

1. **Load Contacts Tab**
   ```
   GET /pages/common/messaging_api.php?action=get_contacts
   (Returns list of employers and advisers)
   ```

2. **Click Contact**
   - Triggers `get_conversation` with empty message history

3. **Type and Send**
   ```
   POST /pages/common/messaging_api.php
   - action: send_message
   - receiver_id: 123
   - receiver_role: employer
   - message: "Hello, I'm interested in..."
   ```

4. **Message Saved to DB**
   - direct_message table gets new row
   - Conversation automatically appears in Conversations tab

### Opening Existing Conversation

1. **Load Conversations Tab**
   ```
   GET /pages/common/messaging_api.php?action=list_conversations
   (Returns all conversations with unread counts)
   ```

2. **Click Conversation**
   ```
   GET /pages/common/messaging_api.php?action=get_conversation
   - other_user_id: 123
   - other_user_role: employer
   (Returns all messages, marks unread as read)
   ```

3. **Messages Display**
   - Conversation thread appears in right pane
   - Ready to send reply

---

## PERFORMANCE OPTIMIZATIONS

### Database Indexes
- Recommend indexes on:
  - `direct_message(sender_id, sender_role, created_at)`
  - `direct_message(receiver_id, receiver_role, is_read)`
  - `messaging_presence(user_id, user_role)`

### Query Limits
- Conversations limited to 100
- Messages per conversation limited to 50
- Contacts limited to 100

### Caching (Future)
- Cache last message per conversation
- Cache unread counts
- Cache presence data (Redis/Memcached)

---

## FUTURE ENHANCEMENTS

### Phase 2
- Typing indicators
- Message reactions (emoji)
- Message search
- Conversation archiving

### Phase 3
- Group conversations
- File/image sharing
- Message forwarding
- Read receipts (time shown)

### Phase 4
- End-to-end encryption
- Message deletion/editing
- Mute notifications
- Message pinning

---

## TROUBLESHOOTING

### No conversations appear
- Check if any direct_message records exist
- Verify user_id and role in session
- Check direct_message table for matching records

### Unread count stuck
- Refresh page to trigger update_presence
- Check messaging_api response for errors

### Messages not sending
- Verify message is not empty
- Check message is under 5000 chars
- Verify receiver_id and receiver_role are valid
- Check database connection

### Online status always offline
- Presence updates every 30 seconds
- Online means last_seen < 5 minutes
- Refresh page to update
- Check messaging_presence table

---

## DATABASE QUERIES

### Get recent conversations with unread counts
```sql
SELECT 
    MAX(CASE WHEN sender_id = 123 THEN receiver_id ELSE sender_id END) as other_id,
    MAX(message_text) as last_msg,
    COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread
FROM direct_message
WHERE (sender_id = 123) OR (receiver_id = 123)
GROUP BY other_id
ORDER BY MAX(created_at) DESC;
```

### Get conversation between two users
```sql
SELECT * FROM direct_message
WHERE (sender_id = 123 AND receiver_id = 456) 
   OR (sender_id = 456 AND receiver_id = 123)
ORDER BY created_at ASC;
```

### Get online users
```sql
SELECT user_id, user_role, last_seen
FROM messaging_presence
WHERE TIMESTAMPDIFF(MINUTE, last_seen, NOW()) < 5;
```

---

## TESTING CHECKLIST

- [ ] Send message between student and employer
- [ ] Verify message appears instantly in both UIs
- [ ] Mark message as read
- [ ] Search conversations by name
- [ ] Search contacts
- [ ] Open multiple conversations
- [ ] Check unread counts
- [ ] Verify timestamps
- [ ] Test empty message validation
- [ ] Test very long message (>5000 chars)
- [ ] Check online status updates
- [ ] Test presence every 30 seconds
- [ ] Verify contact list shows employers AND advisers
- [ ] Test switching between tabs

---

## API BASE URL
```
/pages/common/messaging_api.php
```

## Response Format
All responses are JSON with structure:
```json
{
  "ok": true|false,
  "error": "error message if ok=false",
  "data": "endpoint-specific data"
}
```

---

**Last Updated**: March 29, 2026
**Status**: Production Ready
**Version**: 1.0
