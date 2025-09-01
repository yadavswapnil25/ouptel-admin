<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $table = 'Wo_Emails';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'email_to',
        'subject',
        'message',
    ];

    /**
     * Get the email template by name (for backward compatibility with old system)
     */
    public static function getByName($name)
    {
        return static::where('email_to', $name)->first();
    }

    /**
     * Get all email templates as key-value pairs
     */
    public static function getAllTemplates()
    {
        return static::pluck('message', 'email_to')->toArray();
    }

    /**
     * Get all email templates with default values
     */
    public static function getAllTemplatesWithDefaults()
    {
        $defaults = [
            'activate' => '',
            'invite' => '',
            'login_with' => '',
            'notification' => '',
            'payment_declined' => '',
            'payment_approved' => '',
            'recover' => '',
            'unusual_login' => '',
            'account_deleted' => '',
        ];

        $existing = static::pluck('message', 'email_to')->toArray();
        
        return array_merge($defaults, $existing);
    }

    /**
     * Get template name for display
     */
    public function getTemplateNameAttribute(): string
    {
        $names = [
            'activate' => 'Activate Account',
            'invite' => 'Invite Email',
            'login_with' => 'Login With',
            'notification' => 'Notification',
            'payment_declined' => 'Payment Declined',
            'payment_approved' => 'Payment Approved',
            'recover' => 'Recover Password',
            'unusual_login' => 'Unusual Login',
            'account_deleted' => 'Account Deleted',
        ];

        return $names[$this->email_to] ?? ucfirst(str_replace('_', ' ', $this->email_to));
    }

    /**
     * Get available template types
     */
    public static function getTemplateTypes(): array
    {
        return [
            'activate' => 'Activate Account',
            'invite' => 'Invite Email',
            'login_with' => 'Login With',
            'notification' => 'Notification',
            'payment_declined' => 'Payment Declined',
            'payment_approved' => 'Payment Approved',
            'recover' => 'Recover Password',
            'unusual_login' => 'Unusual Login',
            'account_deleted' => 'Account Deleted',
        ];
    }
}
