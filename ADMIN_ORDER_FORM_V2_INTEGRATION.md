# Admin Order Form V2 Integration - Complete Documentation

## فارسی | Persian

### خلاصه تغییرات
فرم ثبت سفارش ادمین به طور کامل به موتور قیمتگذاری ماتریسی V2 متصل شده است. دیگر از داده‌های استاتیک استفاده نمی‌کند و تمام گزینه‌ها به صورت پویا از API دریافت می‌شوند.

### ویژگی‌های کلیدی

#### 1. Cascade Filtering (فیلترینگ گام‌به‌گام)
- **قطع کتاب → انواع کاغذ/صحافی**: با انتخاب قطع، فقط کاغذها و صحافی‌های سازگار نمایش داده می‌شوند
- **نوع کاغذ → گرماژ**: فقط گرماژهای موجود برای آن نوع کاغذ در قطع انتخابی
- **گرماژ کاغذ → نوع چاپ**: فقط نوع چاپهایی که قیمت دارند (سیاه‌وسفید، رنگی یا ترکیبی)
- **نوع صحافی → آپشن‌ها**: فقط خدمات اضافی مجاز برای آن نوع صحافی

#### 2. حذف کدهای Legacy
- حذف `v2PricingMatrices` از PHP
- حذف منطق fallback به V1 زمانی که V2 فعال است
- استفاده صرفاً از REST API endpoints

#### 3. بهبود تجربه کاربری
- گزینه‌های غیرمجاز غیرفعال می‌شوند نه حذف
- آپشن‌های غیرفعال با opacity کمتر نمایش داده می‌شوند
- پیام‌های راهنما هنگام نبود داده

### نحوه تست

#### پیش‌نیازها
1. موتور قیمتگذاری V2 باید فعال باشد
2. حداقل یک ماتریس قیمت برای یک قطع کتاب پیکربندی شده باشد
3. محدودیت‌هایی برای تست تعریف شده باشد (مثلاً یک نوع کاغذ ممنوع)

#### سناریوهای تست

##### 1. تست Cascade اصلی
```
1. وارد فرم ثبت سفارش ادمین شوید
2. قطع کتاب را انتخاب کنید
   ✓ باید فقط انواع کاغذ و صحافی مجاز نمایش داده شوند
3. نوع کاغذ را انتخاب کنید
   ✓ باید فقط گرماژهای موجود برای آن کاغذ نمایش داده شوند
4. گرماژ را انتخاب کنید
   ✓ گزینه‌های نوع چاپ باید بر اساس قیمت فیلتر شوند
5. نوع صحافی را انتخاب کنید
   ✓ آپشن‌های غیرمجاز برای آن صحافی باید غیرفعال شوند
```

##### 2. تست محدودیت‌های Print Type
```
1. ماتریسی با print_type محدود تنظیم کنید (مثلاً فقط bw برای گرماژ 60)
2. در فرم، قطع → کاغذ → گرماژ 60 را انتخاب کنید
   ✓ فقط گزینه "سیاه و سفید" باید فعال باشد
   ✓ "رنگی" و "ترکیبی" باید غیرفعال باشند
```

##### 3. تست محدودیت‌های Extras
```
1. ماتریسی با forbidden_extras برای یک نوع صحافی تنظیم کنید
2. در فرم، قطع → نوع صحافی را انتخاب کنید
   ✓ آپشن‌های ممنوع باید opacity کمتر داشته باشند
   ✓ نتوان آنها را انتخاب کرد
```

##### 4. تست فیلتر گرماژ جلد (Cover Weight) ✅ جدید
```
1. ماتریسی با forbidden_cover_weights برای یک نوع صحافی تنظیم کنید
   مثال: برای صحافی "شومیز" فقط گرماژ 250 و 300 مجاز است
2. در فرم، قطع کتاب را انتخاب کنید
3. نوع صحافی "شومیز" را انتخاب کنید
   ✓ فیلد گرماژ جلد باید فقط گرماژهای 250 و 300 را نمایش دهد
   ✓ سایر گرماژها نباید در لیست باشند
4. نوع صحافی دیگری انتخاب کنید
   ✓ گرماژهای جلد باید به‌روز شوند بر اساس محدودیت‌های آن صحافی
```

##### 5. تست تغییر قطع
```
1. قطع اول را انتخاب کنید و تمام فیلدها را پر کنید
2. قطع دیگری انتخاب کنید
   ✓ گزینه‌ها باید بر اساس ماتریس جدید بروزرسانی شوند
   ✓ انتخاب‌های قبلی باید حفظ شوند اگر در ماتریس جدید مجاز باشند
```

### API Endpoints مورد استفاده

#### `/wp-json/tabesh/v1/get-allowed-options`
**Method**: POST  
**Authentication**: Required (nonce)

**Request Body**:
```json
{
  "book_size": "رقعی",
  "current_selection": {
    "paper_type": "تحریر",
    "paper_weight": "70",
    "binding_type": "شومیز"
  }
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "allowed_papers": [...],
    "allowed_bindings": [...],
    "allowed_print_types": [...],
    "allowed_extras": [...]
  }
}
```

### ساختار کد

#### JavaScript Functions
- `updateFormParametersForBookSize()`: دریافت گزینه‌های مجاز از API
- `populateAllowedOptions()`: پر کردن فیلدها با داده‌های API
- `updatePaperWeightsFromAPI()`: بروزرسانی گرماژ بر اساس کاغذ
- `updatePrintTypeAvailability()`: فیلتر کردن نوع چاپ
- `updateExtrasAvailability()`: فیلتر کردن آپشن‌ها
- `updateExtrasCheckboxes()`: غیرفعال کردن extras غیرمجاز
- `updateCoverWeightsAvailability()`: ✅ **جدید** - فیلتر کردن گرماژ جلد
- `updateCoverWeightsDropdown()`: ✅ **جدید** - بروزرسانی منوی کشویی گرماژ جلد

#### CSS Classes
- `.chip-disabled`: برای آپشن‌های غیرفعال
- `.tabesh-aof-chip:has(input:disabled)`: برای مرورگرهای پیشرفته

### مشکلات احتمالی و راه‌حل

#### 1. گزینه‌ها بروزرسانی نمی‌شوند
**علت**: ممکن است API فراخوانی نشده باشد  
**راه‌حل**: کنسول مرورگر را چک کنید، باید log "Tabesh: ..." وجود داشته باشد

#### 2. همه گزینه‌ها غیرفعال هستند
**علت**: ماتریس قیمت احتمالاً اشکال دارد  
**راه‌حل**: تنظیمات قیمت‌گذاری را بررسی کنید، حتماً قیمت‌ها > 0 باشند

#### 3. خطای 403 در API
**علت**: مشکل nonce یا دسترسی  
**راه‌حل**: صفحه را رفرش کنید، اطمینان حاصل کنید کاربر لاگین است

---

## English

### Summary of Changes
The admin order form is now fully integrated with the V2 matrix pricing engine. It no longer uses static data and fetches all options dynamically from the API.

### Key Features

#### 1. Cascade Filtering
- **Book Size → Papers/Bindings**: Upon selecting size, only compatible papers and bindings are shown
- **Paper Type → Weights**: Only available weights for that paper type in selected size
- **Paper Weight → Print Types**: Only print types with configured prices (bw, color, or mixed)
- **Binding Type → Extras**: Only allowed extra services for that binding type

#### 2. Legacy Code Removal
- Removed `v2PricingMatrices` from PHP
- Removed fallback to V1 when V2 is enabled
- Uses only REST API endpoints

#### 3. UX Improvements
- Disallowed options are disabled, not removed
- Disabled options shown with reduced opacity
- Helper messages when data is unavailable

### Testing Guide

#### Prerequisites
1. V2 pricing engine must be enabled
2. At least one pricing matrix configured for a book size
3. Some restrictions defined for testing (e.g., forbidden paper type)

#### Test Scenarios

##### 1. Main Cascade Test
```
1. Open admin order form
2. Select book size
   ✓ Only allowed paper types and bindings should appear
3. Select paper type
   ✓ Only available weights for that paper should appear
4. Select weight
   ✓ Print type options should be filtered based on pricing
5. Select binding type
   ✓ Disallowed extras for that binding should be disabled
```

##### 2. Print Type Restrictions Test
```
1. Configure matrix with restricted print_type (e.g., only bw for weight 60)
2. In form, select size → paper → weight 60
   ✓ Only "بیاه و سفید" option should be enabled
   ✓ "رنگی" and "ترکیبی" should be disabled
```

##### 3. Extras Restrictions Test
```
1. Configure matrix with forbidden_extras for a binding type
2. In form, select size → binding type
   ✓ Forbidden extras should have reduced opacity
   ✓ Should not be selectable
```

##### 4. Cover Weight Filtering Test ✅ NEW
```
1. Configure matrix with forbidden_cover_weights for a binding type
   Example: For "شومیز" binding, only 250 and 300 weights are allowed
2. In form, select book size
3. Select "شومیز" binding type
   ✓ Cover weight field should only show 250 and 300 options
   ✓ Other weights should not appear in the list
4. Change to a different binding type
   ✓ Cover weights should update based on that binding's restrictions
```

##### 5. Book Size Change Test
```
1. Select first size and fill all fields
2. Change to different size
   ✓ Options should update based on new matrix
   ✓ Previous selections should be preserved if allowed in new matrix
```

### Technical Architecture

#### Data Flow
```
User selects book_size
    ↓
JavaScript calls /get-allowed-options API
    ↓
Constraint Manager filters options based on matrix restrictions
    ↓
Allowed options returned to frontend
    ↓
Form fields populated with filtered options
    ↓
Subsequent selections trigger cascade filtering
```

#### Browser Compatibility
- Modern browsers: Full support with `:has()` selector
- Older browsers: Fallback to `.chip-disabled` class (applied by JavaScript)
- Tested: Chrome 90+, Firefox 88+, Safari 14+

### Security

#### CSRF Protection
- All API calls require valid nonce
- Nonce sent via `X-WP-Nonce` header

#### Input Validation
- Server-side: Constraint Manager validates all combinations
- Client-side: Disabled options cannot be selected

#### Output Escaping
- All user input properly escaped using `escapeHtml()`
- API responses sanitized before DOM insertion

### Performance

#### Caching Strategy
- Allowed options cached in DOM data attributes
- API called only when book size changes
- Subsequent field updates use cached data

#### Optimization Tips
- Keep pricing matrices reasonably sized
- Use appropriate forbidden lists instead of zero prices
- Minimize number of extras per binding type

### Maintenance

#### Adding New Parameters
1. Update Constraint Manager to include new parameter
2. Add filtering logic in JavaScript
3. Update API response structure if needed
4. Add CSS for new field states

#### Debugging
Enable WordPress debug mode and check browser console:
```javascript
// Look for these logs
'Tabesh: V2 pricing engine is not enabled'
'Tabesh: No allowed options data found'
'Tabesh: Error fetching allowed options'
```

### Future Enhancements
- [x] Cover weight filtering based on binding type ✅ **Implemented**
- [ ] Live price preview during cascade
- [ ] Validation messages for each field
- [ ] Undo/redo for selections
- [ ] Preset configurations

---

## Version History

### v1.1 (Current)
- **Cover weight cascade filtering** ✅
  - Cover weights now filter based on selected binding type
  - Uses constraint manager's `allowed_cover_weights` API
  - Preserves user selection when possible
  - Auto-selects first valid option

### v1.0
- Initial V2 integration
- Complete cascade filtering
- API-based option loading
- Legacy code removal

### Future Versions
- v1.2: Enhanced validation
- v2.0: Full wizard-style interface
