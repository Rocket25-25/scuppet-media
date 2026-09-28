/**
 * api/contact.js  -  Vercel serverless function for the contact form.
 * Replaces contact-handler.php + csrf-token.php (Vercel cannot run PHP).
 *
 * Required environment variables (Vercel > Project > Settings > Environment Variables):
 *   SMTP_USER        Gmail address that sends the mail
 *   SMTP_PASS        Gmail App Password (16 characters)
 *   NOTIFY_TO_EMAIL  (optional) where enquiries are delivered; defaults to SMTP_USER
 */
const nodemailer = require('nodemailer');

const ALLOWED_SERVICES = [
  'LinkedIn Marketing',
  'Video Editing',
  'Website Development',
  'Social Media Marketing',
  'Other',
];

// Extra domains allowed to post to this endpoint (your own Vercel domain is always allowed).
const EXTRA_ALLOWED_HOSTS = ['scuppetmedia.com'];

const RATE_LIMIT_MS = 30 * 1000;
const lastSubmitByIp = new Map(); // best-effort only (per warm serverless instance)

function cleanText(value, maxLen) {
  return String(value == null ? '' : value)
    .replace(/<[^>]*>/g, '')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, maxLen);
}

function hostOf(url) {
  try {
    return new URL(url).host.toLowerCase().replace(/^www\./, '');
  } catch (e) {
    return '';
  }
}

function sourceAllowed(req) {
  const origin = req.headers.origin || '';
  const referer = req.headers.referer || '';
  if (!origin && !referer) return true; // some privacy tools strip both headers

  const own = String(req.headers.host || '').toLowerCase().replace(/^www\./, '');
  const allowed = [own, ...EXTRA_ALLOWED_HOSTS, 'localhost:3000', '127.0.0.1:3000'];
  const h = hostOf(origin) || hostOf(referer);
  return allowed.includes(h);
}

function isValidName(v) {
  return /^[\p{L}\p{M} .'-]{2,100}$/u.test(v);
}
function isValidEmail(v) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) && v.length <= 254;
}
function isValidPhone(v) {
  return /^\+?[0-9]{7,15}$/.test(v.replace(/[\s\-()]/g, ''));
}

module.exports = async (req, res) => {
  res.setHeader('Cache-Control', 'no-store');
  res.setHeader('X-Content-Type-Options', 'nosniff');

  const reply = (code, success, message, errors) =>
    res.status(code).json({ success, message, errors: errors || {} });

  if (req.method !== 'POST') return reply(405, false, 'Invalid request method.');
  if (!sourceAllowed(req)) return reply(403, false, 'Request rejected.');

  let body = req.body;
  if (typeof body === 'string') {
    try { body = JSON.parse(body); } catch (e) { body = {}; }
  }
  body = body || {};

  // Honeypot - bots fill every field
  if (body.website_url) return reply(200, true, 'Message received.');

  // Basic per-IP rate limit
  const ip = String(req.headers['x-forwarded-for'] || '').split(',')[0].trim() || 'unknown';
  const now = Date.now();
  if (lastSubmitByIp.has(ip) && now - lastSubmitByIp.get(ip) < RATE_LIMIT_MS) {
    return reply(429, false, 'You are submitting too quickly. Please wait a moment and try again.');
  }

  const name = cleanText(body.name, 100);
  const email = String(body.email || '').trim();
  const phone = cleanText(body.phone, 30);
  const company = cleanText(body.company, 150);
  const service = cleanText(body.service, 100);
  const serviceOther = cleanText(body.serviceOther, 150);
  const message = cleanText(body.message, 5000);

  const errors = {};
  if (!name || !isValidName(name)) errors.name = 'Please enter your name.';
  if (!email || !isValidEmail(email)) errors.email = 'Please enter a valid email address.';
  if (phone && !isValidPhone(phone)) errors.phone = 'Please enter a valid phone number.';
  if (!service || !ALLOWED_SERVICES.includes(service)) errors.service = 'Please select a service.';
  if (service === 'Other' && !serviceOther) errors.serviceOther = 'Please specify the service you need.';
  if (!message || message.length < 10) errors.message = 'Please tell us a little about your project (at least 10 characters).';

  if (Object.keys(errors).length) {
    return reply(422, false, 'Please correct the highlighted fields.', errors);
  }

  const SMTP_USER = process.env.SMTP_USER;
  const SMTP_PASS = process.env.SMTP_PASS;
  const NOTIFY_TO = process.env.NOTIFY_TO_EMAIL || SMTP_USER;

  if (!SMTP_USER || !SMTP_PASS) {
    console.error('Contact form: SMTP_USER / SMTP_PASS environment variables are not set on Vercel.');
    return reply(500, false, 'Sorry, something went wrong sending your message. Please try again in a moment or email us directly.');
  }

  const transporter = nodemailer.createTransport({
    host: 'smtp.gmail.com',
    port: 465,
    secure: true,
    auth: { user: SMTP_USER, pass: SMTP_PASS },
    connectionTimeout: 10000,
    greetingTimeout: 10000,
    socketTimeout: 15000,
  });

  const serviceLine = service + (service === 'Other' && serviceOther ? ' - ' + serviceOther : '');
  const firstName = name.split(' ')[0] || 'there';

  const adminMail = {
    from: { name: 'Scuppet Media Website', address: SMTP_USER },
    to: NOTIFY_TO,
    replyTo: { name: name, address: email },
    subject: 'New contact form submission - Scuppet Media',
    text: [
      'NEW CONTACT FORM SUBMISSION',
      '====================================================================',
      '',
      'Name: ' + name,
      'Email: ' + email,
      'Phone: ' + (phone || 'Not provided'),
      'Company: ' + (company || 'Not provided'),
      'Service: ' + serviceLine,
      '',
      'Message:',
      message,
      '',
      '--------------------------------------------------------------------',
      'Submitted: ' + new Date().toLocaleString('en-IN', { timeZone: 'Asia/Kolkata' }) + ' (IST)',
    ].join('\r\n'),
  };

  const userMail = {
    from: { name: 'Scuppet Media', address: SMTP_USER },
    to: { name: name, address: email },
    replyTo: NOTIFY_TO,
    subject: 'We received your message - Scuppet Media',
    text: [
      'Hi ' + firstName + ',',
      '',
      "Thanks for reaching out to Scuppet Media! We've received your message and",
      'will get back to you shortly to start the conversation.',
      '',
      'Here is a copy of what you sent us:',
      '- Service: ' + serviceLine,
      '- Message: ' + message,
      '',
      'If anything above needs correcting, just reply to this email.',
      '',
      'Best,',
      'Scuppet Media',
    ].join('\r\n'),
  };

  // Send both emails at the same time (much faster than one after the other).
  const [adminResult, userResult] = await Promise.allSettled([
    transporter.sendMail(adminMail),
    transporter.sendMail(userMail),
  ]);

  if (adminResult.status === 'rejected') {
    console.error('Contact form: admin notification failed:', adminResult.reason);
    return reply(500, false, 'Sorry, something went wrong sending your message. Please try again in a moment or email us directly.');
  }
  if (userResult.status === 'rejected') {
    // Non-fatal: the enquiry reached you, only the visitor's confirmation failed.
    console.error('Contact form: auto-reply to visitor failed:', userResult.reason);
  }

  lastSubmitByIp.set(ip, now);
  return reply(200, true, 'Message received.');
};