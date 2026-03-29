<?php
if (!function_exists('messaging_e')) {
    function messaging_e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$messagingApiUrl = $baseUrl . '/pages/common/messaging_api.php';
?>

<div class="feed-main" style="max-width:1400px;">
  <div class="panel-card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;height:700px;border-radius:8px;">
    <div style="padding:12px 16px;border-bottom:1px solid #e5e7eb;background:#ffffff;display:flex;align-items:center;gap:10px;">
      <i class="fas fa-search" style="color:#9ca3af;"></i>
      <input type="text" id="msgGlobalSearch" placeholder="Search people and messages..."
        style="border:none;outline:none;background:transparent;flex:1;font-size:.92rem;color:#111827;">
      <span id="msgUnreadBadge" style="display:none;background:#2563eb;color:#fff;border-radius:999px;padding:3px 8px;font-size:.72rem;font-weight:700;"></span>
    </div>

    <div style="display:flex;flex:1;min-height:0;">
    <!-- LEFT PANE: Conversations List -->
    <div style="width:320px;border-right:1px solid #e5e7eb;display:flex;flex-direction:column;background:#ffffff;">

      <!-- Tabs -->
      <div style="display:flex;border-bottom:1px solid #e5e7eb;padding:0 12px;">
        <button id="tabConversations" class="msg-tab active" style="flex:1;padding:12px;border:none;background:none;cursor:pointer;font-weight:600;color:#3b82f6;border-bottom:2px solid #3b82f6;">
          Conversations
        </button>
        <button id="tabContacts" class="msg-tab" style="flex:1;padding:12px;border:none;background:none;cursor:pointer;font-weight:600;color:#6b7280;">
          Contacts
        </button>
      </div>

      <div style="padding:10px 12px;border-bottom:1px solid #eef2f7;">
        <div style="font-size:.74rem;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:8px;">Active</div>
        <div id="msgActiveUsers" style="display:flex;gap:10px;overflow-x:auto;padding-bottom:2px;"></div>
      </div>

      <!-- Messages List -->
      <div id="msgConversationsPane" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;">
        <div style="font-size:.75rem;font-weight:700;color:#6b7280;text-transform:uppercase;padding:12px 12px 8px 12px;">Messages <span id="msgCount" style="background:#e5e7eb;padding:2px 6px;border-radius:4px;font-size:.7rem;margin-left:6px;">0</span></div>
        <div id="msgConversationsList" style="flex:1;overflow-y:auto;"></div>
      </div>

      <!-- Contacts List -->
      <div id="msgContactsPane" style="flex:1;overflow-y:auto;display:none;flex-direction:column;">
        <div style="font-size:.75rem;font-weight:700;color:#6b7280;text-transform:uppercase;padding:12px 12px 8px 12px;">Available Contacts <span id="contactCount" style="background:#e5e7eb;padding:2px 6px;border-radius:4px;font-size:.7rem;margin-left:6px;">0</span></div>
        <div id="msgContactsList" style="flex:1;overflow-y:auto;"></div>
      </div>
    </div>

    <!-- RIGHT PANE: Conversation Details -->
    <div style="flex:1;display:flex;flex-direction:column;background:#ffffff;" id="msgDetailPane">
      <div style="text-align:center;padding:40px 20px;color:#9ca3af;">
        <div style="font-size:.9rem;">Select a conversation to start messaging</div>
      </div>
    </div>

  </div>
  </div>
</div>

<style>
  #msgConversationsList::-webkit-scrollbar,
  #msgDetailPane::-webkit-scrollbar {
    width: 6px;
  }
  #msgConversationsList::-webkit-scrollbar-track,
  #msgDetailPane::-webkit-scrollbar-track {
    background: transparent;
  }
  #msgConversationsList::-webkit-scrollbar-thumb,
  #msgDetailPane::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
  }
  #msgConversationsList::-webkit-scrollbar-thumb:hover,
  #msgDetailPane::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
  }

  .msg-conv-item {
    padding: 12px;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: background-color 0.15s;
  }
  .msg-conv-item:hover {
    background-color: #f9fafb;
  }
  .msg-conv-item.active {
    background-color: #eff6ff;
    border-left: 3px solid #3b82f6;
    padding-left: 9px;
  }

  .msg-thread-item {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    animation: slideIn 0.2s ease-out;
    align-items: flex-end;
  }
  @keyframes slideIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .msg-thread-item.own {
    flex-direction: row-reverse;
    justify-content: flex-start;
  }
  .msg-thread-item.other {
    justify-content: flex-start;
  }
  .msg-thread-item > div:last-child {
    max-width: 65%;
    flex-shrink: 1;
  }
  .msg-thread-item.own > div:last-child {
    text-align: right;
  }
  .msg-thread-bubble {
    padding: 10px 14px;
    border-radius: 10px;
    font-size: .875rem;
    line-height: 1.4;
    word-wrap: break-word;
    display: block;
    max-width: 100%;
  }
  .msg-thread-item.own .msg-thread-bubble {
    background: #3b82f6;
    color: white;
    border-bottom-right-radius: 4px;
  }
  .msg-thread-item.other .msg-thread-bubble {
    background: #e5e7eb;
    color: #1f2937;
    border-bottom-left-radius: 4px;
  }
  .msg-thread-time {
    font-size: .75rem;
    color: #9ca3af;
    margin-top: 4px;
    display: block;
  }

  .msg-composer {
    display: flex;
    flex-direction: column;
    gap: 0;
    width: 100%;
  }

  .msg-composer-input-row {
    display: flex;
    gap: 8px;
    align-items: flex-end;
  }

  .msg-input {
    flex: 1 1 auto;
    width: 100%;
    min-height: 44px;
    max-height: 220px;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    font-size: .875rem;
    line-height: 1.35;
    outline: none;
    resize: none;
    overflow-y: auto;
    box-sizing: border-box;
  }

  .msg-file-preview {
    display: none;
    padding: 10px 14px;
    margin-top: 8px;
    background: #f0f6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    font-size: .8rem;
    color: #1e40af;
  }

  .msg-file-preview.active {
    display: block;
  }

  .msg-send-btn {
    flex: 0 0 auto;
    min-width: 84px;
    height: 44px;
    padding: 0 16px;
    border: none;
    border-radius: 10px;
    background: #3b82f6;
    color: white;
    cursor: pointer;
    font-weight: 600;
  }

  .msg-thread-time {
    font-size: .75rem;
    color: #9ca3af;
    margin-top: 4px;
    display: block;
    cursor: help;
  }

  @media (max-width: 900px) {
    .msg-input {
      min-height: 42px;
      max-height: 180px;
      font-size: .84rem;
      padding: 9px 12px;
    }

    .msg-send-btn {
      min-width: 74px;
      height: 42px;
      padding: 0 12px;
      font-size: .84rem;
    }
  }
</style>

<script>
(function () {
  var apiUrl = <?php echo json_encode($messagingApiUrl); ?>;
  var currentUserId = <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : '0'; ?>;
  var currentUserRole = <?php echo json_encode($_SESSION['role'] ?? 'student'); ?>;
  var activeConv = null;
  var allConversations = [];
  var allContacts = [];
  var activeFilterTerm = '';

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    var k = 1024;
    var sizes = ['B', 'KB', 'MB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  function renderMessageContent(messageText) {
    var html = '';
    var remaining = messageText;
    
    // Process message for images and files
    var imgRegex = /\[IMG\](.*?)\|(.*?)\|(\d+)\[\/IMG\]/g;
    var fileRegex = /\[FILE\](.*?)\|(.*?)\|(\d+)\[\/FILE\]/g;
    var lastIndex = 0;
    
    var imgMatch;
    var fileMatch;
    var allMatches = [];
    
    // Find all image and file matches with their positions
    while ((imgMatch = imgRegex.exec(messageText)) !== null) {
      allMatches.push({
        type: 'img',
        index: imgMatch.index,
        match: imgMatch,
        name: imgMatch[1],
        url: imgMatch[2],
        size: parseInt(imgMatch[3])
      });
    }
    
    fileRegex.lastIndex = 0;
    while ((fileMatch = fileRegex.exec(messageText)) !== null) {
      allMatches.push({
        type: 'file',
        index: fileMatch.index,
        match: fileMatch,
        name: fileMatch[1],
        url: fileMatch[2],
        size: parseInt(fileMatch[3])
      });
    }
    
    // Sort by position
    allMatches.sort(function(a, b) { return a.index - b.index; });
    
    // Build output with interleaved text and attachments
    lastIndex = 0;
    allMatches.forEach(function(item) {
      // Add text before this attachment
      if (item.index > lastIndex) {
        var textBefore = messageText.substring(lastIndex, item.index);
        html += '<div style="margin-bottom:8px;word-break:break-word;">' + escapeHtml(textBefore.trim()) + '</div>';
      }
      
      // Add attachment
      if (item.type === 'img') {
        html += '<div style="margin-top:8px;margin-bottom:8px;">'
          + '<img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(item.name) + '" style="max-width:280px;max-height:300px;border-radius:8px;cursor:pointer;" onclick="window.open(\'' + escapeHtml(item.url) + '\', \'_blank\')">'
          + '</div>';
      } else if (item.type === 'file') {
        var fileExt = item.name.split('.').pop().toUpperCase();
        html += '<div style="margin-top:8px;margin-bottom:8px;background:rgba(0,0,0,0.05);border-radius:8px;padding:12px;display:flex;align-items:center;gap:12px;cursor:pointer;" onclick="window.open(\'' + escapeHtml(item.url) + '\', \'_blank\')">'
          + '<div style="font-size:24px;">📄</div>'
          + '<div style="flex:1;min-width:0;">'
          + '<div style="font-weight:600;font-size:.85rem;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(item.name) + '</div>'
          + '<div style="font-size:.75rem;color:#6b7280;margin-top:2px;">' + formatFileSize(item.size) + '</div>'
          + '</div>'
          + '<div style="color:#3b82f6;font-size:20px;">⬇</div>'
          + '</div>';
      }
      
      lastIndex = item.index + item.match[0].length;
    });
    
    // Add remaining text
    if (lastIndex < messageText.length) {
      var textAfter = messageText.substring(lastIndex);
      if (textAfter.trim()) {
        html += '<div style="margin-bottom:8px;word-break:break-word;">' + escapeHtml(textAfter.trim()) + '</div>';
      }
    }
    
    // If no attachments found, just escape and display as regular text
    if (allMatches.length === 0) {
      html = '<div style="word-break:break-word;">' + escapeHtml(messageText) + '</div>';
    }
    
    return html;
  }

  function getInitials(name) {
    var parts = String(name || '').trim().split(' ');
    return parts.map(function(p) { return p.charAt(0).toUpperCase(); }).join('').slice(0, 2) || '?';
  }

  function getAvatarColor(name) {
    var sum = 0;
    for (var i = 0; i < name.length; i++) {
      sum += name.charCodeAt(i);
    }
    var colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#6366f1', '#ef4444'];
    return colors[sum % colors.length];
  }

  function renderAvatar(name, profilePic, size) {
    size = size || '40px';
    if (profilePic && profilePic.trim() !== '') {
      return '<img src="' + escapeHtml(profilePic) + '" alt="' + escapeHtml(name) + '" style="width:' + size + ';height:' + size + ';border-radius:50%;object-fit:cover;object-position:center;">';
    }
    return '<div style="width:' + size + ';height:' + size + ';border-radius:50%;background:' + getAvatarColor(name) + ';color:white;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;">' + getInitials(name) + '</div>';
  }

  function callApi(action, params, options) {
    var requestOptions = options || {};
    var separator = apiUrl.indexOf('?') === -1 ? '?' : '&';
    var url = apiUrl + separator + 'action=' + encodeURIComponent(action);

    var payload = params || {};

    if (!requestOptions.method) {
      requestOptions.method = 'GET';
    }

    requestOptions.cache = 'no-store';

    if (requestOptions.method.toUpperCase() === 'GET') {
      Object.keys(payload).forEach(function(key) {
        url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(payload[key]);
      });
      url += '&_t=' + Date.now();
    } else {
      var formData = new FormData();
      Object.keys(payload).forEach(function(key) {
        formData.append(key, payload[key]);
      });
      requestOptions.body = formData;
    }

    return fetch(url, requestOptions)
      .then(function (res) { return res.ok ? res.json() : Promise.reject('API error'); })
      .then(function (data) { return data.ok ? data : Promise.reject(data.error || 'API error'); });
  }

  function renderConversationsList(conversations, searchTerm) {
    var convList = document.getElementById('msgConversationsList');

    var filtered = conversations;
    if (searchTerm) {
      filtered = conversations.filter(function(c) {
        return c.other_user_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
               c.last_message.toLowerCase().includes(searchTerm.toLowerCase());
      });
    }

    if (filtered.length === 0) {
      convList.innerHTML = '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:.875rem;">No conversations</div>';
      document.getElementById('msgCount').textContent = '0';
      return;
    }

    document.getElementById('msgCount').textContent = filtered.length;

    var html = filtered.map(function(conv) {
      var isActive = activeConv && activeConv.other_user_id === conv.other_user_id && activeConv.other_user_role === conv.other_user_role;

      return '<div class="msg-conv-item' + (isActive ? ' active' : '') + '" data-id="' + conv.other_user_id + '" data-role="' + escapeHtml(conv.other_user_role) + '">'
        + '<div style="display:flex;gap:10px;align-items:flex-start;">'
        + '<div style="width:40px;height:40px;flex-shrink:0;">'
        + renderAvatar(conv.other_user_name, conv.other_user_profile_picture, '40px')
        + '</div>'
        + '<div style="flex:1;min-width:0;">'
        + '<div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;">'
        + '<div style="font-weight:600;color:#111827;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(conv.other_user_name) + '</div>'
        + '<div style="font-size:.75rem;color:#9ca3af;white-space:nowrap;">' + escapeHtml(conv.last_message_time) + '</div>'
        + '</div>'
        + '<div style="font-size:.8rem;color:#6b7280;margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(conv.last_message) + '</div>'
        + '</div>'
        + (conv.unread_count > 0 ? '<div style="width:20px;height:20px;background:#3b82f6;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0;">' + conv.unread_count + '</div>' : '')
        + '</div>'
        + '</div>';
    }).join('');

    convList.innerHTML = html;

    convList.querySelectorAll('.msg-conv-item').forEach(function(el) {
      el.addEventListener('click', function() {
        showConversation(parseInt(this.getAttribute('data-id')), this.getAttribute('data-role'));
      });
    });
  }

  function renderActiveUsers(contacts) {
    var activeEl = document.getElementById('msgActiveUsers');
    if (!activeEl) {
      return;
    }

    var onlineContacts = (contacts || []).filter(function(c) {
      return !!c.is_online;
    }).slice(0, 12);

    if (onlineContacts.length === 0) {
      activeEl.innerHTML = '<div style="font-size:.8rem;color:#9ca3af;">No one active right now</div>';
      return;
    }

    activeEl.innerHTML = onlineContacts.map(function(contact) {
      return '<button type="button" class="msg-active-btn" data-id="' + contact.user_id + '" data-role="' + escapeHtml(contact.user_role) + '" style="position:relative;border:none;background:none;padding:0;cursor:pointer;">'
        + '<div style="width:36px;height:36px;">'
        + renderAvatar(contact.name, contact.profile_picture, '36px')
        + '</div>'
        + '<span style="position:absolute;right:-2px;bottom:-1px;width:10px;height:10px;border-radius:50%;background:#22c55e;border:2px solid #fff;"></span>'
        + '</button>';
    }).join('');

    activeEl.querySelectorAll('.msg-active-btn').forEach(function(el) {
      el.addEventListener('click', function() {
        showConversation(parseInt(this.getAttribute('data-id'), 10), this.getAttribute('data-role'));
      });
    });
  }

  function updateUnreadBadge() {
    var unreadBadge = document.getElementById('msgUnreadBadge');
    if (!unreadBadge) {
      return;
    }

    var unreadTotal = (allConversations || []).reduce(function(sum, item) {
      return sum + (parseInt(item.unread_count || 0, 10) || 0);
    }, 0);

    if (unreadTotal > 0) {
      unreadBadge.style.display = 'inline-block';
      unreadBadge.textContent = unreadTotal + ' unread';
    } else {
      unreadBadge.style.display = 'none';
      unreadBadge.textContent = '';
    }
  }

  function renderContactsList(contacts, searchTerm) {
    var contactList = document.getElementById('msgContactsList');

    var filtered = contacts;
    if (searchTerm) {
      filtered = contacts.filter(function(c) {
        return c.name.toLowerCase().includes(searchTerm.toLowerCase());
      });
    }

    if (filtered.length === 0) {
      contactList.innerHTML = '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:.875rem;">No contacts</div>';
      document.getElementById('contactCount').textContent = '0';
      return;
    }

    document.getElementById('contactCount').textContent = filtered.length;

    var html = filtered.map(function(contact) {
      return '<div class="msg-contact-item" data-id="' + contact.user_id + '" data-role="' + escapeHtml(contact.user_role) + '" style="padding:12px;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background-color 0.15s;display:flex;gap:10px;align-items:center;">'
        + '<div style="width:40px;height:40px;flex-shrink:0;">'
        + renderAvatar(contact.name, contact.profile_picture, '40px')
        + '</div>'
        + '<div style="flex:1;min-width:0;">'
        + '<div style="font-weight:600;color:#111827;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(contact.name) + '</div>'
        + '<div style="font-size:.75rem;color:#9ca3af;margin-top:2px;">' + escapeHtml(contact.role_label) + (contact.headline ? ' • ' + escapeHtml(contact.headline) : '') + '</div>'
        + '</div>'
        + '<div style="color:#3b82f6;font-size:.8rem;font-weight:600;">→</div>'
        + '</div>';
    }).join('');

    contactList.innerHTML = html;

    contactList.querySelectorAll('.msg-contact-item').forEach(function(el) {
      el.addEventListener('click', function() {
        showConversation(parseInt(this.getAttribute('data-id')), this.getAttribute('data-role'));
        switchTab('conversations');
      });
      el.addEventListener('mouseover', function() {
        this.style.backgroundColor = '#f9fafb';
      });
      el.addEventListener('mouseout', function() {
        this.style.backgroundColor = 'transparent';
      });
    });
  }

  function switchTab(tab) {
    var convPane = document.getElementById('msgConversationsPane');
    var contactPane = document.getElementById('msgContactsPane');
    var convTab = document.getElementById('tabConversations');
    var contactTab = document.getElementById('tabContacts');

    if (tab === 'conversations') {
      convPane.style.display = 'flex';
      contactPane.style.display = 'none';
      convTab.style.color = '#3b82f6';
      convTab.style.borderBottomColor = '#3b82f6';
      contactTab.style.color = '#6b7280';
      contactTab.style.borderBottomColor = 'transparent';
    } else {
      convPane.style.display = 'none';
      contactPane.style.display = 'flex';
      convTab.style.color = '#6b7280';
      convTab.style.borderBottomColor = 'transparent';
      contactTab.style.color = '#3b82f6';
      contactTab.style.borderBottomColor = '#3b82f6';
    }
  }

  function showConversation(otherId, otherRole) {
    // Call API to get conversation messages
    callApi('get_conversation', {
      other_user_id: otherId,
      other_user_role: otherRole
    }).then(function(data) {
      activeConv = {
        other_user_id: otherId,
        other_user_role: otherRole,
        other_user_name: data.other_user_name
      };

      var detailPane = document.getElementById('msgDetailPane');
      var messagesHtml = '';
      var onlineLabel = data.is_online ? 'Active now' : (data.last_seen || 'Offline');
      var headline = data.other_user_headline || data.other_user_role || '';
      var email = data.other_user_email || '';
      var profilePath = data.other_user_profile_path || '';
      var profilePicture = data.other_user_profile_picture || '';

      if (data.messages && data.messages.length > 0) {
        messagesHtml = data.messages.map(function(msg) {
          var isOwn = msg.sender_id === currentUserId && msg.sender_role === currentUserRole;
          var senderName = isOwn ? 'You' : data.other_user_name;
          var senderPic = isOwn ? '' : profilePicture;

          return '<div class="msg-thread-item' + (isOwn ? ' own' : ' other') + '">'
            + '<div style="flex-shrink:0;width:32px;height:32px;margin-top:2px;">'
            + renderAvatar(senderName, senderPic, '32px')
            + '</div>'
            + '<div style="flex:1;min-width:0;">'
            + '<div style="font-size:.75rem;color:#6b7280;margin-bottom:2px;' + (isOwn ? 'text-align:right;' : '') + '">' + escapeHtml(senderName) + '</div>'
            + '<div class="msg-thread-bubble">' + renderMessageContent(msg.message_text) + '</div>'
            + '<div class="msg-thread-time">' + escapeHtml(msg.time_label) + '</div>'
            + '</div>'
            + '</div>';
        }).join('');
      }

      var html = '<div style="display:flex;flex-direction:column;height:100%;">'
        + '<div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;gap:16px;">'
        + '<div style="display:flex;flex-direction:column;align-items:center;min-width:70px;">'
        + '<div style="width:56px;height:56px;">' + renderAvatar(data.other_user_name, profilePicture, '56px') + '</div>'
        + '</div>'
        + '<div style="flex:1;min-width:0;">'
        + '<div style="font-weight:700;color:#111827;font-size:.95rem;margin-bottom:2px;">' + escapeHtml(data.other_user_name) + '</div>'
        + '<div style="font-size:.77rem;color:' + (data.is_online ? '#16a34a' : '#6b7280') + ';margin-bottom:4px;">● ' + escapeHtml(onlineLabel) + '</div>'
        + '<div style="font-size:.8rem;color:#475569;margin-bottom:2px;">' + (headline ? escapeHtml(headline) : 'Profile available') + '</div>'
        + '<div style="font-size:.75rem;color:#6b7280;">' + (email ? escapeHtml(email) : '') + '</div>'
        + '</div>'
        + (profilePath ? '<a href="' + escapeHtml(profilePath) + '" class="btn btn-ghost btn-sm" style="text-decoration:none;white-space:nowrap;">View Profile</a>' : '')
        + '</div>'
        + '<div style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:4px;">'
        + messagesHtml
        + '</div>'
        + '<div style="padding:16px 20px;border-top:1px solid #e5e7eb;flex-shrink:0;">'
        + '<div class="msg-composer">'
        + '<input type="file" id="msgFileInput" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp" style="display:none;">'
        + '<div class="msg-composer-input-row">'
        + '<button id="msgAttachBtn" type="button" style="flex:0 0 auto;width:44px;height:44px;border:1px solid #d1d5db;border-radius:10px;background:#fff;color:#3b82f6;cursor:pointer;font-size:1.2rem;display:flex;align-items:center;justify-content:center;"><i class="fas fa-paperclip"></i></button>'
        + '<textarea placeholder="Send a message..." id="msgInput" class="msg-input" rows="1"></textarea>'
        + '<button id="msgSendBtn" class="msg-send-btn" type="button">Send</button>'
        + '</div>'
        + '<div id="msgFilePreview" class="msg-file-preview"></div>'
        + '</div>'
        + '</div>'
        + '</div>';

      detailPane.innerHTML = html;

      var sendBtn = document.getElementById('msgSendBtn');
      var input = document.getElementById('msgInput');

      function autoResizeComposer() {
        if (!input) {
          return;
        }

        input.style.height = 'auto';
        var maxHeight = window.matchMedia('(max-width: 900px)').matches ? 180 : 220;
        var nextHeight = Math.min(input.scrollHeight, maxHeight);
        input.style.height = Math.max(nextHeight, 44) + 'px';
      }

      autoResizeComposer();
      input.addEventListener('input', function() {
        autoResizeComposer();
        // Show typing indicator
        callApi('update_presence', {}, {method: 'POST'}).catch(function() {});
      });

      var fileInput = document.getElementById('msgFileInput');
      var attachBtn = document.getElementById('msgAttachBtn');
      var filePreview = document.getElementById('msgFilePreview');
      var selectedFile = null;

      attachBtn.addEventListener('click', function() {
        fileInput.click();
      });

      fileInput.addEventListener('change', function() {
        selectedFile = this.files[0];
        if (selectedFile) {
          var maxSize = 10 * 1024 * 1024; // 10MB
          if (selectedFile.size > maxSize) {
            alert('File size must be less than 10MB');
            selectedFile = null;
            return;
          }
          filePreview.innerHTML = '📎 ' + escapeHtml(selectedFile.name) + ' (' + (selectedFile.size / 1024 / 1024).toFixed(2) + 'MB)';
          filePreview.classList.add('active');
        }
      });

      sendBtn.addEventListener('click', function() {
        var message = input.value.trim();
        if (!message && !selectedFile) return;

        if (!selectedFile) {
          // Send text message only
          callApi('send_message', {
            receiver_id: otherId,
            receiver_role: otherRole,
            message: message
          }, { method: 'POST' }).then(function() {
            input.value = '';
            autoResizeComposer();
            selectedFile = null;
              filePreview.classList.remove('active');
            loadConversations();
            showConversation(otherId, otherRole);
          }).catch(function(err) {
            alert(err || 'Failed to send message');
          });
        } else {
          // Send file with optional message
          var formData = new FormData();
          formData.append('action', 'send_message');
          formData.append('receiver_id', otherId);
          formData.append('receiver_role', otherRole);
          formData.append('message', message);
          formData.append('file', selectedFile);

          fetch(apiUrl, {
            method: 'POST',
            body: formData,
            cache: 'no-store'
          }).then(function(res) { return res.ok ? res.json() : Promise.reject('API error'); })
            .then(function(data) { return data.ok ? data : Promise.reject(data.error || 'API error'); })
            .then(function() {
              input.value = '';
              autoResizeComposer();
              selectedFile = null;
              filePreview.classList.remove('active');
              loadConversations();
              showConversation(otherId, otherRole);
            }).catch(function(err) {
              alert(err || 'Failed to send file');
            });
        }
      });

      input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendBtn.click();
        }
      });

      renderConversationsList(allConversations);
    }).catch(function(err) {
      alert('Failed to load conversation');
    });
  }

  function loadConversations() {
    callApi('list_conversations', {}).then(function(data) {
      allConversations = data.conversations || [];
      renderConversationsList(allConversations, activeFilterTerm);
      updateUnreadBadge();
    }).catch(function() {
      document.getElementById('msgConversationsList').innerHTML = '<div style="padding:20px;text-align:center;color:#ef4444;font-size:.875rem;">Unable to load messages</div>';
    });
  }

  function loadContacts() {
    callApi('get_contacts', {}).then(function(data) {
      allContacts = data.contacts || [];
      renderContactsList(allContacts, activeFilterTerm);
      renderActiveUsers(allContacts);
    }).catch(function() {
      document.getElementById('msgContactsList').innerHTML = '<div style="padding:20px;text-align:center;color:#ef4444;font-size:.875rem;">Unable to load contacts</div>';
    });
  }

  // Tab switching
  document.getElementById('tabConversations').addEventListener('click', function() {
    switchTab('conversations');
  });

  document.getElementById('tabContacts').addEventListener('click', function() {
    switchTab('contacts');
  });

  document.getElementById('msgGlobalSearch').addEventListener('input', function(e) {
    activeFilterTerm = e.target.value || '';
    var activePane = document.getElementById('msgConversationsPane');
    if (activePane.style.display !== 'none') {
      renderConversationsList(allConversations, activeFilterTerm);
    } else {
      renderContactsList(allContacts, activeFilterTerm);
    }
  });

  loadConversations();
  loadContacts();
  
  // Update presence every 30 seconds
  setInterval(function() {
    callApi('update_presence', {}, { method: 'POST' }).catch(function() {});
  }, 30000);
})();
</script>
