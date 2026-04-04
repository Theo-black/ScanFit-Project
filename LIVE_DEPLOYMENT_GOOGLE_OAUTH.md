# ScanFit Live Deployment Updates (Google Sign-Up/Login)

## 1. Upload/Update These Files on Live

1. `functions.php`
2. `login.php`
3. `register.php`
4. `google_login.php` (new)
5. `google_callback.php` (new)

## 2. Google Cloud Console Settings

In your OAuth Client (Web application), set:

- **Authorized redirect URI**:  
  `https://scan-fit.kesug.com/google_callback.php`

## 3. Live Environment Variables

Set these values in your live environment (or live `.env` if used):

```env
SCANFIT_GOOGLE_CLIENT_ID=866960030394-9gco7sbqvv4jcs0oj1gf46vgf6lc312h.apps.googleusercontent.com
SCANFIT_GOOGLE_CLIENT_SECRET=YOUR_FULL_REAL_SECRET_HERE
SCANFIT_GOOGLE_REDIRECT_URI=https://scan-fit.kesug.com/google_callback.php
```

Notes:

- Replace `YOUR_FULL_REAL_SECRET_HERE` with the full unmasked secret from Google Console.
- Do not use the masked/partial value.
- Keep the local/shared repo `.env` free of Google OAuth secrets until you are ready to roll this out.
- The current codebase is designed so Google buttons stay hidden until all three Google OAuth variables are set.

## 4. Optional SSL CA Bundle Setting (Only If Needed)

Only add this if live server shows SSL/cURL certificate errors:

```env
SCANFIT_CA_BUNDLE=/absolute/path/to/cacert.pem
```

Most Linux hosts do not need this if system CA certificates are already configured.

## 5. Post-Deploy Test Checklist

1. Open `https://scan-fit.kesug.com/login.php`
2. Confirm **Continue with Google** button appears.
3. Click it and complete Google sign-in.
4. Confirm redirect back to site and logged-in session is created.
5. Open `https://scan-fit.kesug.com/register.php`
6. Confirm **Sign up with Google** works as well.

## 6. Security Follow-Up

Because the client secret was shared in chat, rotate it in Google Cloud Console and update live credentials with the new secret.
