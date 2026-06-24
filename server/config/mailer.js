import nodemailer from 'nodemailer';
import Setting from '../models/Setting.js';

const sendEmail = async ({ to, subject, html }) => {
  let transporter;

  // Try to load SMTP settings from database
  let smtpSettings = null;
  try {
    const smtpRecord = await Setting.findOne({ key: 'smtp_settings' });
    if (smtpRecord && smtpRecord.value) {
      smtpSettings = smtpRecord.value;
    }
  } catch (err) {
    console.error('[Mailer] Error loading SMTP settings from DB:', err.message);
  }

  const smtpUser = smtpSettings?.smtp_user || process.env.SMTP_USER;
  const smtpPass = smtpSettings?.smtp_pass || process.env.SMTP_PASS;
  const smtpHost = smtpSettings?.smtp_host || process.env.SMTP_HOST || 'smtp.gmail.com';
  const smtpPort = parseInt(smtpSettings?.smtp_port) || parseInt(process.env.SMTP_PORT) || 587;
  const smtpSecure = smtpSettings ? (smtpSettings.smtp_port === '465') : (process.env.SMTP_SECURE === 'true');

  if (smtpUser && smtpPass) {
    console.log(`[Mailer] Sending actual email to ${to} via SMTP (${smtpHost}:${smtpPort})...`);
    transporter = nodemailer.createTransport({
      host: smtpHost,
      port: smtpPort,
      secure: smtpSecure,
      auth: {
        user: smtpUser,
        pass: smtpPass
      }
    });
  } else {
    console.log('[Mailer] SMTP credentials not found in database or server/.env. Falling back to Ethereal Email test account...');
    const testAccount = await nodemailer.createTestAccount();
    transporter = nodemailer.createTransport({
      host: 'smtp.ethereal.email',
      port: 587,
      secure: false,
      auth: {
        user: testAccount.user,
        pass: testAccount.pass
      }
    });
  }

  const fromAddress = smtpUser || 'no-reply@searchhomesindia.com';
  const mailOptions = {
    from: `"Search Homes India" <${fromAddress}>`,
    to,
    subject,
    html
  };

  const info = await transporter.sendMail(mailOptions);
  
  if (!smtpUser) {
    const previewUrl = nodemailer.getTestMessageUrl(info);
    console.log('[Mailer] Test Email sent successfully!');
    console.log(`[Mailer] Test Mail Preview URL: ${previewUrl}`);
  }

  return info;
};

export default sendEmail;
