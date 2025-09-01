<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'email_to' => 'activate',
                'subject' => 'Activate Your Account',
                'message' => '<p>Hello {{USERNAME}},</p><p>Thank you for registering with {{SITE_NAME}}!</p><p>To activate your account, please click the link below:</p><p><a href="{{SITE_URL}}/activate/{{CODE}}">Activate Account</a></p><p>If you did not create an account, please ignore this email.</p><p>Best regards,<br>{{SITE_NAME}} Team</p>',
            ],
            [
                'email_to' => 'invite',
                'subject' => 'You have been invited',
                'message' => '<p>Hi there,</p><p>You have received an invitation request from your friend <a href="{{URL}}">{{NAME}}</a> to join {{SITE_NAME}}!</p><p>Click the link below to accept the invitation:</p><p><a href="{{URL}}">Accept Invitation</a></p><p>Best regards,<br>{{SITE_NAME}} Team</p>',
            ],
            [
                'email_to' => 'login_with',
                'subject' => 'Login Notification',
                'message' => '<p>Hello {{FIRST_NAME}},</p><p>Thank you for joining {{SITE_NAME}}!</p><p>Your temporary login credentials:</p><p>Username: {{USERNAME}}<br>Email: {{EMAIL}}</p><p>Please change your password after your first login.</p><p>Best regards,<br>{{SITE_NAME}} Team</p>',
            ],
            [
                'email_to' => 'notification',
                'subject' => 'New Notification',
                'message' => '<p>Hello,</p><p>You have a new notification from {{SITE_NAME}}.</p><p>{{TEXT_TYPE}}: {{TEXT}}</p><p><a href="{{NOTIFY_URL}}">View Notification</a></p><p>Best regards,<br>{{SITE_NAME}} Team</p>',
            ],
            [
                'email_to' => 'payment_declined',
                'subject' => 'Payment Declined',
                'message' => '<p>Hello {{name}},</p><p>Unfortunately your payment of ${{amount}} was declined.</p><p>Please check your payment method and try again.</p><p>Best regards,<br>{{site_name}} Team</p>',
            ],
            [
                'email_to' => 'payment_approved',
                'subject' => 'Payment Approved',
                'message' => '<p>Hello {{name}},</p><p>Your payment of ${{amount}} has been approved.</p><p>Thank you for your purchase!</p><p>Best regards,<br>{{site_name}} Team</p>',
            ],
            [
                'email_to' => 'recover',
                'subject' => 'Password Recovery',
                'message' => '<p>Hello {{USERNAME}},</p><p>You requested to recover your password for {{SITE_NAME}}.</p><p>Click the link below to reset your password:</p><p><a href="{{LINK}}">Reset Password</a></p><p>If you did not request this, please ignore this email.</p><p>Best regards,<br>{{SITE_NAME}} Team</p>',
            ],
            [
                'email_to' => 'unusual_login',
                'subject' => 'Unusual Login Detected',
                'message' => '<p>Hello {{USERNAME}},</p><p>We detected an unusual login to your {{SITE_NAME}} account.</p><p>Details:</p><p>Date: {{DATE}}<br>Email: {{EMAIL}}<br>Country: {{COUNTRY_CODE}}<br>IP Address: {{IP_ADDRESS}}<br>City: {{CITY}}</p><p>If this was not you, please secure your account immediately.</p><p>Best regards,<br>{{SITE_NAME}} Team</p>',
            ],
            [
                'email_to' => 'account_deleted',
                'subject' => 'Account Deleted',
                'message' => '<p>Hello {{USERNAME}},</p><p>Your account has been successfully deleted from {{SITE_NAME}}.</p><p>We are sorry to see you go. If you change your mind, you can always create a new account.</p><p>Best regards,<br>{{SITE_NAME}} Team</p>',
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['email_to' => $template['email_to']],
                $template
            );
        }
    }
}


