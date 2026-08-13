<x-mail::message>
# Password Reset Request

You've requested to reset your BizLedger password. Click the button below to proceed:

<x-mail::button :url="$resetUrl">
Reset Password
</x-mail::button>

This link will expire in 60 minutes.

If you didn't request this password reset, you can ignore this email. No action is required.

Thanks,<br>
{{ config('app.name') }} Team

---

**Security Note:** Never share this link with anyone. Your password reset link is unique and should not be forwarded.
</x-mail::message>
