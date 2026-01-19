<?php

namespace App\Core\Services;

use App\Models\Brand;
use App\Models\NotificationTemplate;

class TemplateRendererService
{
    /**
     * Render template with variables
     */
    public function render(NotificationTemplate $template, array $data = []): array
    {
        $subject = $this->renderString($template->subject ?? '', $data);
        $body = $this->renderString($template->body, $data);

        // Inject branding if email channel
        if ($template->channel === 'email') {
            $body = $this->injectBranding($body, $data);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Render a string with variables
     */
    protected function renderString(string $content, array $data): string
    {
        // Replace {{variable}} placeholders
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($data) {
            $key = $matches[1];
            return $data[$key] ?? $matches[0]; // Return original if not found
        }, $content);
    }

    /**
     * Inject branding tokens into email body
     */
    protected function injectBranding(string $body, array $data): string
    {
        $brand = Brand::where('is_published', true)->first();
        
        $branding = [
            'app_name' => $brand->name ?? config('app.name'),
            'app_logo' => $brand->logo_url ?? '',
            'primary_color' => '#3B82F6', // Default, can be from theme
            'support_email' => $brand->support_email ?? '',
            'support_phone' => $brand->support_phone ?? '',
        ];

        // Replace branding tokens
        foreach ($branding as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return $body;
    }

    /**
     * Preview render with sample data
     */
    public function preview(NotificationTemplate $template, string $sampleType = 'order'): array
    {
        $sampleData = app(SampleDataFactory::class)->getSample($sampleType);
        return $this->render($template, $sampleData);
    }
}

