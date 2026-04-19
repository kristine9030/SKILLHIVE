const http = require('http');
const fs = require('fs');
const path = require('path');
const nodemailer = require('nodemailer');

const PORT = Number(process.env.EMAIL_API_PORT || 3100);
const EMAIL_PROVIDER = String(process.env.EMAIL_PROVIDER || '').trim().toLowerCase();
const GMAIL_USER = String(process.env.GMAIL_SMTP_USER || process.env.GMAIL_USER || '').trim();
const GMAIL_APP_PASSWORD = String(process.env.GMAIL_APP_PASSWORD || '').replace(/\s+/g, '');
const BREVO_SMTP_LOGIN = String(process.env.BREVO_SMTP_LOGIN || '').trim();
const BREVO_SMTP_KEY = String(process.env.BREVO_SMTP_KEY || '').replace(/\s+/g, '');
const BREVO_SMTP_HOST = String(process.env.BREVO_SMTP_HOST || 'smtp-relay.brevo.com').trim();
const BREVO_SMTP_PORT = Number(process.env.BREVO_SMTP_PORT || 587);
const EMAIL_FROM_NAME = String(process.env.EMAIL_FROM_NAME || 'SkillHive Adviser Team').trim();
const EMAIL_FROM_ADDRESS = String(process.env.EMAIL_FROM_ADDRESS || '').trim();
const EMAIL_SUBJECT = String(process.env.EMAIL_SUBJECT || 'SkillHive Notification').trim();
const EMAIL_PREDEFINED_MESSAGE = String(
  process.env.EMAIL_PREDEFINED_MESSAGE ||
    'Hello! This is a quick update from your internship adviser in SkillHive.'
).trim();
const EMAIL_HEADER_LOGO_URL = String(
  process.env.EMAIL_HEADER_LOGO_URL ||
    'https://dummyimage.com/220x60/111827/ffffff&text=SkillHive'
).trim();
const EMAIL_HEADER_LOGO_PATH = String(process.env.EMAIL_HEADER_LOGO_PATH || '').trim();

function sendJson(res, statusCode, payload) {
  res.writeHead(statusCode, {
    'Content-Type': 'application/json; charset=UTF-8',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
  });
  res.end(JSON.stringify(payload));
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim());
}

function escapeHtml(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function buildLogoConfig(logoUrlOverride) {
  const logoUrl = String(logoUrlOverride || '').trim();
  if (/^https?:\/\//i.test(logoUrl)) {
    return { src: logoUrl, attachments: [] };
  }

  if (EMAIL_HEADER_LOGO_PATH) {
    const resolvedLogoPath = path.resolve(EMAIL_HEADER_LOGO_PATH);
    if (fs.existsSync(resolvedLogoPath)) {
      const cid = 'skillhive-logo@skillhive';
      return {
        src: 'cid:' + cid,
        attachments: [
          {
            filename: path.basename(resolvedLogoPath),
            path: resolvedLogoPath,
            cid,
          },
        ],
      };
    }
  }

  return { src: EMAIL_HEADER_LOGO_URL, attachments: [] };
}

function buildEmailHtml(studentName, messageText, logoSrc) {
  const safeName = escapeHtml(String(studentName || 'Student'));
  const safeMessage = escapeHtml(String(messageText || EMAIL_PREDEFINED_MESSAGE)).replace(/\r?\n/g, '<br>');
  const safeLogoSrc = escapeHtml(String(logoSrc || EMAIL_HEADER_LOGO_URL));

  return `
  <div style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#1f2937;">
    <div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
      <div style="padding:22px 24px;text-align:center;border-bottom:1px solid #e5e7eb;">
        <img src="${safeLogoSrc}" alt="SkillHive" style="max-width:220px;width:100%;height:auto;display:inline-block;">
      </div>
      <div style="padding:24px;line-height:1.65;">
        <h2 style="margin:0 0 12px;font-size:22px;color:#111827;">Hello, ${safeName}!</h2>
        <p style="margin:0 0 12px;font-size:15px;color:#374151;">${safeMessage}</p>
        <p style="margin:0;font-size:15px;color:#374151;">If you have questions, please coordinate with your adviser through SkillHive.</p>
      </div>
      <div style="padding:14px 24px;text-align:center;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
        This email was sent automatically by SkillHive.
      </div>
    </div>
  </div>`;
}

function resolveEmailProvider(payload) {
  const payloadProvider = String((payload && payload.provider) || '').trim().toLowerCase();
  if (payloadProvider === 'brevo' || payloadProvider === 'gmail') {
    return payloadProvider;
  }

  if (EMAIL_PROVIDER === 'brevo' || EMAIL_PROVIDER === 'gmail') {
    return EMAIL_PROVIDER;
  }

  if (BREVO_SMTP_LOGIN && BREVO_SMTP_KEY) {
    return 'brevo';
  }

  return 'gmail';
}

function resolveTransportConfig(payload) {
  const provider = resolveEmailProvider(payload);

  if (provider === 'brevo') {
    const payloadLogin = String((payload && payload.smtpLogin) || '').trim();
    const payloadKey = String((payload && payload.smtpKey) || '').replace(/\s+/g, '');
    const payloadFromEmail = String((payload && payload.fromEmail) || '').trim();

    const login = payloadLogin || BREVO_SMTP_LOGIN;
    const key = payloadKey || BREVO_SMTP_KEY;
    const fromAddress = payloadFromEmail || EMAIL_FROM_ADDRESS || login;

    return {
      provider,
      host: BREVO_SMTP_HOST,
      port: BREVO_SMTP_PORT,
      secure: BREVO_SMTP_PORT === 465,
      authUser: login,
      authPass: key,
      fromAddress,
    };
  }

  return {
    provider: 'gmail',
    host: 'smtp.gmail.com',
    port: 465,
    secure: true,
    authUser: GMAIL_USER,
    authPass: GMAIL_APP_PASSWORD,
    fromAddress: EMAIL_FROM_ADDRESS || GMAIL_USER,
  };
}

function createTransporter(config) {
  return nodemailer.createTransport({
    host: config.host,
    port: config.port,
    secure: config.secure,
    auth: {
      user: config.authUser,
      pass: config.authPass,
    },
  });
}

async function handleSendEmail(req, res) {
  let rawBody = '';
  req.on('data', (chunk) => {
    rawBody += chunk;
    if (rawBody.length > 1024 * 1024) {
      req.destroy();
    }
  });

  req.on('end', async () => {
    let payload;
    try {
      payload = JSON.parse(rawBody || '{}');
    } catch (error) {
      sendJson(res, 400, { ok: false, error: 'Invalid JSON body.' });
      return;
    }

    const studentEmail = String(payload.studentEmail || '').trim();
    const studentName = String(payload.studentName || 'Student').trim();
    const messageText = String(payload.message || '').trim() || EMAIL_PREDEFINED_MESSAGE;
    const subjectText = String(payload.subject || '').trim() || EMAIL_SUBJECT;
    const logoUrl = String(payload.logoUrl || '').trim();

    if (!isValidEmail(studentEmail)) {
      sendJson(res, 422, { ok: false, error: 'A valid student email is required.' });
      return;
    }

    const transportConfig = resolveTransportConfig(payload);
    if (!transportConfig.authUser || !transportConfig.authPass) {
      const providerLabel = transportConfig.provider === 'brevo' ? 'Brevo SMTP' : 'Gmail SMTP';
      sendJson(res, 500, {
        ok: false,
        error: 'Email service is not configured. Missing ' + providerLabel + ' credentials.',
      });
      return;
    }

    if (!isValidEmail(transportConfig.fromAddress)) {
      sendJson(res, 500, {
        ok: false,
        error: 'Email service is not configured. Invalid sender email address.',
      });
      return;
    }

    try {
      const transporter = createTransporter(transportConfig);
      const logoConfig = buildLogoConfig(logoUrl);
      const info = await transporter.sendMail({
        from: `"${EMAIL_FROM_NAME}" <${transportConfig.fromAddress}>`,
        to: studentEmail,
        subject: subjectText,
        html: buildEmailHtml(studentName, messageText, logoConfig.src),
        attachments: logoConfig.attachments,
      });

      sendJson(res, 200, {
        ok: true,
        messageId: info.messageId || null,
      });
    } catch (error) {
      const providerLabel = transportConfig.provider === 'brevo' ? 'Brevo SMTP' : 'Gmail SMTP';
      sendJson(res, 500, {
        ok: false,
        error: 'Failed to send email. Check ' + providerLabel + ' credentials and sender verification.',
      });
    }
  });
}

const server = http.createServer((req, res) => {
  if (req.method === 'OPTIONS') {
    sendJson(res, 204, { ok: true });
    return;
  }

  if (req.method === 'POST' && req.url === '/send-email') {
    handleSendEmail(req, res);
    return;
  }

  sendJson(res, 404, { ok: false, error: 'Route not found.' });
});

server.listen(PORT, () => {
  console.log(`SkillHive email API running on http://localhost:${PORT}`);
});
