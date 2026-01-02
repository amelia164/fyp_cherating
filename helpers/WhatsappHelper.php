<?php

class WhatsappHelper
{
    /**
     * Sends a direct WhatsApp message using TextMeBot API
    */
    public static function sendOTP($phone, $message)
    {
        $apiKey = "FzhUF92sDGAv"; // Your actual key
        
        // 1. Clean the phone number (remove +, spaces, dashes)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        // 2. Ensure it has the country code (default to 60 for Malaysia if it starts with 0)
        if (strpos($cleanPhone, '0') === 0) {
            $cleanPhone = '6' . $cleanPhone;
        }

        $url = "https://api.textmebot.com/send.php?phone=" . $cleanPhone . "&apikey=" . $apiKey . "&text=" . urlencode($message);

        // 3. Use cURL instead of file_get_contents for better reliability
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if(curl_errno($ch)) {
            error_log('TextMeBot cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        return $response;
    }

    /**
     * Generates a wa.me link (used for the contact form)
    */
    public static function generateWhatsAppLink($name, $email, $phone, $message)
    {
        $whatsappNumber = '601140471172'; // Guest house owner's WhatsApp

        $text = urlencode(
            "📩 Welcome to Cherating Guest House!\n\nPlease find the submitted details:\n\n" .
            "👤 Name: $name\n" .
            "📧 Email: $email\n" .
            "📱 Phone: $phone\n" .
            "💬 Message: $message"
        );

        return "https://wa.me/$whatsappNumber?text=$text";
    }
}