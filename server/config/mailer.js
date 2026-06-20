import nodemailer from 'nodemailer';

const sendEmail = async ({ to, subject, html }) => {
  let transporter;

  const smtpUser = process.env.SMTP_USER;
  const smtpPass = process.env.SMTP_PASS;

  if (smtpUser && smtpPass) {
    console.log(`[Mailer] Sending actual email to ${to} via SMTP...`);
    transporter = nodemailer.createTransport({
      host: process.env.SMTP_HOST || 'smtp.gmail.com',
      port: parseInt(process.env.SMTP_PORT) || 587,
      secure: process.env.SMTP_SECURE === 'true', // true for 465, false for 587
      auth: {
        user: smtpUser,
        pass: smtpPass
      }
    });
  } else {
    console.log('[Mailer] SMTP credentials not found in server/.env. Falling back to Ethereal Email test account...');
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
    // Save preview URL to global variable or output for testing convenience
  }

  return info;
};

export default sendEmail;
