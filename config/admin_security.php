<?php

return [
  'login_max_attempts' => (int) env('ADMIN_LOGIN_MAX_ATTEMPTS', 5),
  'login_lockout_minutes' => (int) env('ADMIN_LOGIN_LOCKOUT_MINUTES', 15),
  'login_captcha_after' => (int) env('ADMIN_LOGIN_CAPTCHA_AFTER', 3),
  'password_min_length' => (int) env('ADMIN_PASSWORD_MIN_LENGTH', 16),
  'password_remind_days' => (int) env('ADMIN_PASSWORD_REMIND_DAYS', 90),
];
