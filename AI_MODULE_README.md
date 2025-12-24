# Tabesh AI Module

## ماژول هوش مصنوعی تابش

A comprehensive, modular AI integration for the Tabesh book printing order management system.

یک ماژول هوش مصنوعی کامل و مستقل برای سامانه مدیریت سفارش چاپ کتاب تابش.

## Features / ویژگی‌ها

### ✨ Core Features / ویژگی‌های اصلی

- **Modular Architecture** / معماری مستقل
  - Complete isolation from core plugin / جدا از هسته اصلی افزونه
  - Can be enabled/disabled without affecting core functionality / قابل فعال/غیرفعال بودن بدون تأثیر بر عملکرد اصلی
  - Zero dependencies on other plugin components / بدون وابستگی به سایر اجزای افزونه

- **Multiple AI Providers** / چند ارائه‌دهنده هوش مصنوعی
  - OpenAI GPT (GPT-3.5, GPT-4, GPT-4 Turbo)
  - Google Gemini (Gemini Pro, Gemini Pro Vision)
  - xAI Grok (Grok Beta, Grok 1)
  - DeepSeek (DeepSeek Chat, DeepSeek Coder)
  - Extensible architecture for adding more providers / معماری قابل توسعه برای افزودن ارائه‌دهندگان جدید

- **Specialized AI Assistants** / دستیارهای تخصصی هوش مصنوعی
  - **Order Assistant** / دستیار سفارشات: Helps with order management and inquiries
  - **User Support** / پشتیبانی کاربر: General user help and troubleshooting
  - **Admin Tools** / ابزار مدیریت: Data analysis and insights for administrators

- **Role-Based Access Control** / کنترل دسترسی مبتنی بر نقش
  - Each assistant has configurable role restrictions / هر دستیار دارای محدودیت نقش قابل تنظیم
  - WordPress native role integration / یکپارچگی با نقش‌های بومی وردپرس
  - Fine-grained capability control / کنترل دقیق قابلیت‌ها

- **REST API Integration** / یکپارچگی با REST API
  - `/wp-json/tabesh/v1/ai/query` - Query AI assistants
  - `/wp-json/tabesh/v1/ai/assistants` - Get available assistants
  - Proper authentication and permission checks / بررسی احراز هویت و مجوز مناسب

## Installation / نصب

The AI module is included in Tabesh plugin v1.1.0+. No separate installation is required.

ماژول هوش مصنوعی در نسخه ۱.۱.۰ و بالاتر افزونه تابش گنجانده شده است. نیازی به نصب جداگانه نیست.

## Configuration / پیکربندی

### Enable the Module / فعال‌سازی ماژول

1. Go to **Tabesh → Settings → AI Settings**
   برو به **تابش → تنظیمات → تنظیمات هوش مصنوعی**

2. Check "Enable AI Module" / "ماژول هوش مصنوعی را فعال کن" را تیک بزنید

3. Click "Save Settings" / روی "ذخیره تنظیمات" کلیک کنید

### Configure AI Models / پیکربندی مدل‌های هوش مصنوعی

For each AI provider you want to use:

1. Check the model to activate it / مدل را تیک بزنید تا فعال شود
2. Enter the API key / کلید API را وارد کنید
3. Select the specific model variant / نوع خاص مدل را انتخاب کنید
4. Save settings / تنظیمات را ذخیره کنید

#### Getting API Keys / دریافت کلیدهای API

- **OpenAI GPT**: https://platform.openai.com/api-keys
- **Google Gemini**: https://makersuite.google.com/app/apikey
- **xAI Grok**: https://x.ai/api
- **DeepSeek**: https://platform.deepseek.com/api_keys

## Usage / نحوه استفاده

### Via REST API / از طریق REST API

```javascript
// Query an AI assistant
fetch('/wp-json/tabesh/v1/ai/query', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        assistant_id: 'order',
        query: 'چگونه قیمت سفارش را محاسبه کنم؟',
        context: {}
    })
})
.then(response => response.json())
.then(data => {
    console.log(data.message); // AI response
});
```

### Get Available Assistants / دریافت دستیارهای موجود

```javascript
fetch('/wp-json/tabesh/v1/ai/assistants', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
})
.then(response => response.json())
.then(data => {
    console.log(data.assistants); // Array of available assistants
});
```

## Extending the AI Module / توسعه ماژول هوش مصنوعی

### Adding a Custom AI Model / افزودن مدل سفارشی

```php
<?php
/**
 * Custom AI Model Example
 */
class My_Custom_AI_Model extends Tabesh_AI_Model_Base {
    
    public function __construct() {
        $this->model_id      = 'my_custom_model';
        $this->model_name    = 'My Custom AI';
        $this->api_endpoint  = 'https://api.example.com/v1/chat';
        $this->max_tokens    = 4096;
        $this->config_fields = array(
            'api_key' => array(
                'label'       => 'API Key',
                'type'        => 'text',
                'required'    => true,
                'description' => 'Enter your API key',
            ),
        );
    }
    
    public function generate( $prompt, $context = array(), $options = array() ) {
        // Implement API call logic
        // Return array with 'success' and 'data' or 'error' keys
    }
}

// Register the custom model
add_action( 'tabesh_ai_register_models', function( $ai ) {
    $custom_model = new My_Custom_AI_Model();
    $ai->register_model( $custom_model );
} );
```

### Adding a Custom Assistant / افزودن دستیار سفارشی

```php
<?php
/**
 * Custom Assistant Example
 */
class My_Custom_Assistant extends Tabesh_AI_Assistant_Base {
    
    public function __construct() {
        $this->assistant_id          = 'my_assistant';
        $this->assistant_name        = 'My Custom Assistant';
        $this->assistant_description = 'Helps with custom tasks';
        $this->allowed_roles         = array( 'administrator' );
        $this->capabilities          = array( 'custom_task' );
        $this->system_prompt         = 'You are a helpful assistant...';
        $this->preferred_model       = 'gpt';
    }
    
    protected function prepare_context( $context ) {
        // Add custom context data
        $context['custom_data'] = 'value';
        return $context;
    }
}

// Register the custom assistant
add_action( 'tabesh_ai_register_assistants', function( $ai ) {
    $custom_assistant = new My_Custom_Assistant();
    $ai->register_assistant( $custom_assistant );
} );
```

### Filtering Assistant Access / فیلتر کردن دسترسی دستیار

```php
<?php
// Customize assistant access based on custom logic
add_filter( 'tabesh_ai_assistant_can_access', function( $has_access, $user_id, $assistant_id ) {
    // Custom access logic
    if ( $assistant_id === 'admin_tools' ) {
        // Only allow specific users
        $allowed_users = array( 1, 2, 3 );
        return in_array( $user_id, $allowed_users );
    }
    return $has_access;
}, 10, 3 );
```

## Architecture / معماری

### Directory Structure / ساختار دایرکتوری

```
includes/ai/
├── class-tabesh-ai.php                    # Main AI controller
├── class-tabesh-ai-model-base.php         # Base class for models
├── class-tabesh-ai-assistant-base.php     # Base class for assistants
├── interfaces/
│   ├── interface-tabesh-ai-model.php      # Model interface
│   └── interface-tabesh-ai-assistant.php  # Assistant interface
├── models/
│   ├── class-tabesh-ai-model-gpt.php      # OpenAI GPT
│   ├── class-tabesh-ai-model-gemini.php   # Google Gemini
│   ├── class-tabesh-ai-model-grok.php     # xAI Grok
│   └── class-tabesh-ai-model-deepseek.php # DeepSeek
└── assistants/
    ├── class-tabesh-ai-assistant-order.php       # Order assistant
    ├── class-tabesh-ai-assistant-user-help.php   # User help
    └── class-tabesh-ai-assistant-admin-tools.php # Admin tools
```

### Design Patterns / الگوهای طراحی

- **Singleton Pattern**: Main AI controller
- **Strategy Pattern**: Interchangeable AI models
- **Interface Segregation**: Clear contracts for models and assistants
- **Dependency Injection**: Loose coupling via WordPress hooks
- **Registry Pattern**: Model and assistant registration

## Security / امنیت

- ✅ All API requests require authentication / تمام درخواست‌های API نیاز به احراز هویت دارند
- ✅ Role-based access control / کنترل دسترسی مبتنی بر نقش
- ✅ Input sanitization / پاکسازی ورودی
- ✅ Output escaping / Escape کردن خروجی
- ✅ Nonce verification / تأیید nonce
- ✅ Prepared database queries / کوئری‌های آماده‌شده پایگاه داده

## Performance / کارایی

- ⚡ Lazy loading: AI module only loads when enabled
- ⚡ Settings caching: Reduces database queries
- ⚡ Async API calls: Non-blocking operations
- ⚡ Minimal overhead when disabled

## Troubleshooting / عیب‌یابی

### AI Module Not Showing in Settings / ماژول هوش مصنوعی در تنظیمات نمایش داده نمی‌شود

1. Check WordPress version (requires 6.8+)
2. Check PHP version (requires 8.2.2+)
3. Verify file permissions
4. Clear WordPress cache

### API Calls Failing / فراخوانی‌های API شکست می‌خورند

1. Verify API keys are correct / کلیدهای API را تأیید کنید
2. Check internet connectivity / اتصال اینترنت را بررسی کنید
3. Verify API provider status / وضعیت ارائه‌دهنده API را بررسی کنید
4. Check WordPress debug log / لاگ دیباگ وردپرس را بررسی کنید

### Assistant Not Accessible / دستیار قابل دسترسی نیست

1. Verify user role permissions / مجوزهای نقش کاربر را تأیید کنید
2. Check if AI module is enabled / بررسی کنید که ماژول هوش مصنوعی فعال است
3. Verify at least one model is configured / تأیید کنید حداقل یک مدل پیکربندی شده است

## Hooks Reference / مرجع هوک‌ها

### Actions

- `tabesh_ai_initialized` - Fired when AI module is initialized
- `tabesh_ai_model_registered` - Fired when a model is registered
- `tabesh_ai_assistant_registered` - Fired when an assistant is registered
- `tabesh_ai_register_models` - Hook to register custom models
- `tabesh_ai_register_assistants` - Hook to register custom assistants

### Filters

- `tabesh_ai_is_enabled` - Filter AI module enabled status
- `tabesh_ai_models` - Filter registered models
- `tabesh_ai_assistants` - Filter registered assistants
- `tabesh_ai_assistant_can_access` - Filter assistant access control

## Support / پشتیبانی

For support and bug reports, please contact:
- GitHub Issues: https://github.com/tabshhh4-sketch/Tabesh/issues
- Website: https://chapco.ir

## License / مجوز

GPL v2 or later

## Changelog / تغییرات

### Version 1.1.0
- Initial release of AI module
- Support for 4 AI providers (GPT, Gemini, Grok, DeepSeek)
- 3 specialized assistants
- Full REST API integration
- Role-based access control
