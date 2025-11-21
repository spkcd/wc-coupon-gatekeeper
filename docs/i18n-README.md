# Internationalization (i18n) Guide
## WC Coupon Gatekeeper Plugin

---

## Overview

The WC Coupon Gatekeeper plugin is fully internationalization-ready with consistent text domain usage and proper translation function implementation.

**Text Domain:** `wc-coupon-gatekeeper`  
**Domain Path:** `/languages`  
**Total Translatable Strings:** ~127

---

## Translation Status

### Current Implementation ✅

| Aspect | Status | Details |
|--------|--------|---------|
| Text Domain | ✅ Complete | `wc-coupon-gatekeeper` used consistently |
| Domain Path | ✅ Set | `/languages` in plugin header |
| Load Text Domain | ✅ Implemented | Loaded on `plugins_loaded` hook |
| Translation Functions | ✅ Complete | All strings wrapped |
| Translator Comments | ✅ Complete | Context provided where needed |
| Plural Forms | ✅ Implemented | Using `_n()` function |

---

## Generating POT File

### Method 1: WP-CLI (Recommended)

If you have WP-CLI installed:

```bash
# Navigate to plugin directory
cd /path/to/wp-content/plugins/wc-coupon-gatekeeper

# Generate POT file
wp i18n make-pot . languages/wc-coupon-gatekeeper.pot

# Verify POT file created
ls -lh languages/wc-coupon-gatekeeper.pot
```

**Expected Output:**
```
Plugin Name: WC Coupon Gatekeeper
POT File: languages/wc-coupon-gatekeeper.pot
Strings Extracted: ~127
```

### Method 2: Poedit (GUI Tool)

1. Download and install [Poedit](https://poedit.net/)
2. Open Poedit
3. Click "File → New from Code"
4. Select plugin root directory
5. Set Translation Properties:
   - Project Name: `WC Coupon Gatekeeper`
   - Team Email: `your-email@example.com`
   - Language: `en_US`
   - Source Paths: `.` (current directory)
   - Excluded Paths: `node_modules, tests, .git`
6. Set Source Keywords:
   - `__`
   - `_e`
   - `_n:1,2`
   - `_x:1,2c`
   - `_ex:1,2c`
   - `_nx:1,2,4c`
   - `esc_attr__`
   - `esc_attr_e`
   - `esc_attr_x:1,2c`
   - `esc_html__`
   - `esc_html_e`
   - `esc_html_x:1,2c`
7. Click "Extract from Sources"
8. Save as `languages/wc-coupon-gatekeeper.pot`

### Method 3: grunt-wp-i18n (Build Tool)

```bash
# Install grunt-wp-i18n
npm install grunt-wp-i18n --save-dev

# Create Gruntfile.js
cat > Gruntfile.js << 'EOF'
module.exports = function(grunt) {
    grunt.initConfig({
        makepot: {
            target: {
                options: {
                    domainPath: '/languages',
                    mainFile: 'wc-coupon-gatekeeper.php',
                    potFilename: 'wc-coupon-gatekeeper.pot',
                    type: 'wp-plugin',
                    updateTimestamp: true
                }
            }
        }
    });
    
    grunt.loadNpmTasks('grunt-wp-i18n');
    grunt.registerTask('default', ['makepot']);
};
EOF

# Run grunt
grunt
```

### Method 4: Manual POT Template

If no tools available, create `languages/wc-coupon-gatekeeper.pot` manually:

```pot
# Copyright (C) 2024 WC Coupon Gatekeeper
# This file is distributed under the GPL v2 or later.
msgid ""
msgstr ""
"Project-Id-Version: WC Coupon Gatekeeper 1.0.0\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/wc-coupon-gatekeeper\n"
"POT-Creation-Date: 2024-01-01 00:00:00+00:00\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"PO-Revision-Date: 2024-MO-DA HO:MI+ZONE\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"
"Language-Team: LANGUAGE <LL@li.org>\n"
"X-Generator: WP-CLI\n"

# Add strings manually...
```

---

## Creating Translations

### Step 1: Generate MO File

After translating PO file:

```bash
# Using WP-CLI
wp i18n make-mo languages/

# Using msgfmt (gettext tools)
msgfmt -o languages/wc-coupon-gatekeeper-es_ES.mo languages/wc-coupon-gatekeeper-es_ES.po
```

### Step 2: Install Translation

Place files in:
```
wp-content/languages/plugins/
├── wc-coupon-gatekeeper-es_ES.mo
├── wc-coupon-gatekeeper-es_ES.po
├── wc-coupon-gatekeeper-fr_FR.mo
└── wc-coupon-gatekeeper-fr_FR.po
```

Or in plugin directory:
```
wp-content/plugins/wc-coupon-gatekeeper/languages/
├── wc-coupon-gatekeeper-es_ES.mo
└── wc-coupon-gatekeeper-es_ES.po
```

---

## Translatable Strings by Category

### Admin Interface (Settings)

- Feature toggle labels and descriptions
- Coupon targeting options
- Day restriction settings
- Monthly limit settings
- Message customization labels
- Advanced settings labels

**Example Strings:**
```php
__( 'Coupon Gatekeeper', 'wc-coupon-gatekeeper' )
__( 'Enable Day-of-Month Restriction', 'wc-coupon-gatekeeper' )
__( 'Restrict coupons to specific days of each month.', 'wc-coupon-gatekeeper' )
```

### Admin Interface (Logs)

- Logs screen title and labels
- Table column headers
- Filter options
- Bulk action labels
- Success/error messages

**Example Strings:**
```php
__( 'Coupon Gatekeeper Logs', 'wc-coupon-gatekeeper' )
__( 'Gatekeeper Logs', 'wc-coupon-gatekeeper' )
_n( '%d usage record reset.', '%d usage records reset.', $count, 'wc-coupon-gatekeeper' )
```

### Error Messages

- Day restriction errors
- Monthly limit errors
- Validation errors
- Permission errors

**Example Strings:**
```php
__( 'This coupon can only be used on the allowed day(s) each month.', 'wc-coupon-gatekeeper' )
__( "You've already used this coupon this month.", 'wc-coupon-gatekeeper' )
__( 'You do not have permission to manage WooCommerce settings.', 'wc-coupon-gatekeeper' )
```

### Success/Info Messages

- Success notices
- Fallback day explanations
- Confirmation messages

**Example Strings:**
```php
__( 'Nice timing! This coupon is valid today.', 'wc-coupon-gatekeeper' )
__( "Coupon valid today because the configured day doesn't occur this month.", 'wc-coupon-gatekeeper' )
__( 'Old logs have been purged successfully.', 'wc-coupon-gatekeeper' )
```

### Order Notes

- Usage increment notes
- Usage decrement notes
- Tracking notes

**Example Strings:**
```php
__( 'Coupon Gatekeeper: Usage incremented for "%1$s" in %2$s.', 'wc-coupon-gatekeeper' )
__( 'Coupon Gatekeeper: Usage decremented for "%1$s" in %2$s.', 'wc-coupon-gatekeeper' )
```

---

## String Extraction Reference

### Translation Functions Used

| Function | Usage | Count |
|----------|-------|-------|
| `__()` | Basic translation | 67 |
| `_e()` | Echo translation | 0 |
| `_n()` | Plural forms | 4 |
| `_x()` | Context translation | 0 |
| `esc_html__()` | Escaped translation | 43 |
| `esc_html_e()` | Echo escaped translation | 8 |
| `esc_attr__()` | Attribute translation | 5 |

### Translator Comments

The plugin includes translator comments for context:

```php
sprintf(
    /* translators: %s: WooCommerce plugin link */
    __( '<strong>WC Coupon Gatekeeper</strong> requires WooCommerce...', 'wc-coupon-gatekeeper' ),
    $link
);

sprintf(
    /* translators: 1: coupon code, 2: month */
    __( 'Coupon Gatekeeper: Usage incremented for "%1$s" in %2$s.', 'wc-coupon-gatekeeper' ),
    $coupon_code,
    $order_month
);
```

---

## Testing Translations

### Step 1: Install Test Language

```bash
# Download WordPress language pack
wp language core install es_ES

# Activate language
wp site switch-language es_ES
```

### Step 2: Test Plugin Strings

1. Navigate to WordPress admin
2. Go to WooCommerce → Settings → Coupon Gatekeeper
3. Verify all labels are translated
4. Test error messages on frontend
5. Check order notes in Spanish
6. Verify admin logs interface

### Step 3: Validate MO File

```bash
# Check MO file integrity
msgfmt -c -v languages/wc-coupon-gatekeeper-es_ES.po
```

---

## Translation Platforms

### WordPress.org

For official WordPress.org distribution:

1. Plugin approved on WordPress.org
2. Translators contribute via translate.wordpress.org
3. Language packs auto-generated
4. Auto-installed for users

**URL Pattern:**
```
https://translate.wordpress.org/projects/wp-plugins/wc-coupon-gatekeeper/
```

### GlotPress (Self-hosted)

For self-hosted translation:

1. Install GlotPress plugin
2. Import POT file
3. Enable translation interface
4. Contributors translate strings
5. Export PO/MO files

### Translation Memory

Leverage WordPress.org translation memory:
- Consistent terminology across plugins
- Auto-suggestions from similar plugins
- Community-validated translations

---

## Best Practices

### For Developers

1. **Always use text domain**
   ```php
   // ✅ Good
   __( 'Error message', 'wc-coupon-gatekeeper' );
   
   // ❌ Bad
   __( 'Error message' );
   ```

2. **Use placeholders for dynamic content**
   ```php
   // ✅ Good
   sprintf( __( 'Coupon %s applied.', 'wc-coupon-gatekeeper' ), $code );
   
   // ❌ Bad
   __( 'Coupon ' . $code . ' applied.', 'wc-coupon-gatekeeper' );
   ```

3. **Provide translator comments**
   ```php
   /* translators: %s: coupon code */
   sprintf( __( 'Using %s', 'wc-coupon-gatekeeper' ), $code );
   ```

4. **Handle plurals correctly**
   ```php
   // ✅ Good
   sprintf(
       _n( '%d item', '%d items', $count, 'wc-coupon-gatekeeper' ),
       $count
   );
   
   // ❌ Bad
   __( $count . ' items', 'wc-coupon-gatekeeper' );
   ```

### For Translators

1. **Maintain consistent terminology**
   - Use same translation for same English term
   - Follow WordPress and WooCommerce style guides
   - Review existing WooCommerce translations

2. **Preserve placeholders**
   ```
   Original: "Coupon %s applied successfully."
   Spanish: "Cupón %s aplicado correctamente."
   ```

3. **Test in context**
   - Install plugin with translation
   - Test all admin screens
   - Test frontend messages
   - Verify proper character encoding

4. **Report issues**
   - Ambiguous strings
   - Missing translator comments
   - Concatenated strings
   - Hardcoded text

---

## Language Packs

### Priority Language Packs

Consider prioritizing these languages based on WooCommerce usage:

1. **Spanish** (es_ES) - Latin America & Spain
2. **German** (de_DE) - Germany, Austria, Switzerland
3. **French** (fr_FR) - France, Belgium, Canada
4. **Italian** (it_IT) - Italy
5. **Portuguese** (pt_BR) - Brazil
6. **Dutch** (nl_NL) - Netherlands
7. **Polish** (pl_PL) - Poland
8. **Russian** (ru_RU) - Russia
9. **Chinese** (zh_CN) - China
10. **Japanese** (ja) - Japan

---

## Continuous Integration

### Automate POT Generation

Add to GitHub Actions:

```yaml
name: Generate POT File

on:
  push:
    branches: [ main ]

jobs:
  pot:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup WP-CLI
        run: |
          curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
          chmod +x wp-cli.phar
          sudo mv wp-cli.phar /usr/local/bin/wp
      - name: Generate POT
        run: |
          wp i18n make-pot . languages/wc-coupon-gatekeeper.pot --allow-root
      - name: Commit POT
        run: |
          git config user.name "GitHub Actions"
          git config user.email "actions@github.com"
          git add languages/wc-coupon-gatekeeper.pot
          git commit -m "Update POT file" || echo "No changes"
          git push || echo "No changes to push"
```

---

## Support

### Translation Questions

For translation-related questions:
- Check [WordPress Polyglots Handbook](https://make.wordpress.org/polyglots/handbook/)
- Visit [WordPress.org Support Forums](https://wordpress.org/support/)
- Join [WordPress Slack #polyglots channel](https://wordpress.slack.com/archives/C02RP50LK)

### Report Issues

Report translation bugs:
1. Open issue on plugin repository
2. Include locale code (e.g., es_ES)
3. Provide screenshot if applicable
4. Suggest correct translation

---

## Checklist: Adding New Translatable String

- [ ] Wrap string in translation function (`__`, `_e`, `_n`, etc.)
- [ ] Include text domain: `wc-coupon-gatekeeper`
- [ ] Add translator comment if needed
- [ ] Use sprintf for dynamic content
- [ ] Test string extraction
- [ ] Regenerate POT file
- [ ] Verify string appears in POT
- [ ] Test with translated language

---

## Resources

- [WordPress I18n Documentation](https://developer.wordpress.org/plugins/internationalization/)
- [WooCommerce Translation Guide](https://woocommerce.com/document/woocommerce-localization/)
- [Poedit Download](https://poedit.net/)
- [GlotPress](https://wordpress.org/plugins/glotpress/)
- [WP-CLI i18n Commands](https://developer.wordpress.org/cli/commands/i18n/)

---

**Last Updated:** 2024  
**Plugin Version:** 1.0.0  
**Strings Count:** ~127