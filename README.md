# Allpay Payment Plugin for VirtueMart (Joomla)

This is the official **Allpay** payment gateway plugin for **VirtueMart**, a popular e-commerce solution for Joomla.

---

## 🚀 Features

- Seamless integration with [Allpay](https://allpay.co.il)
- Secure payment redirection and notification handling
- Support for VAT and installment payments
- Configurable API credentials and minimum order amount for installments
- Compatible with Joomla 4.x, 5.x and VirtueMart 4.x

---

## 🧩 Requirements

- Joomla 4.0+
- VirtueMart 4.0+
- PHP 7.4 or higher
- cURL enabled on your server

---

## 📦 Installation

1. Download the latest `.zip` release of the plugin from [Releases](https://github.com/your-repo-url/releases).
2. In your Joomla Admin panel, go to:  
   `Extensions → Manage → Install`
3. Upload the plugin zip file.
4. After successful installation, go to:  
   `Extensions → Plugins → Type: vmpayment → Allpay`
5. Enable the plugin.
6. Fill in your **Allpay API Login** and **API Key**.

---

## ⚙️ Configuration

Navigate to:

`VirtueMart → Shop → Payment Methods → New`

- **Payment Name**: Allpay
- **Published**: Yes
- **Payment Method**: VM Payment - Allpay
- In the **Configuration** tab:
  - Set your Allpay API credentials
  - Choose VAT settings
  - (Optional) Enable and configure installment settings

---

## 🔄 Order Flow

1. After order confirmation, the user is redirected to Allpay’s secure payment page.
2. Upon payment completion, Allpay sends a signed JSON notification back to the site.
3. The plugin validates the signature, updates the order status to **Confirmed**, and displays the result.

---

## 🔐 Webhook Notification

Make sure your site can accept notifications at: 

https://<your-domain>/index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&method=allpay


---

## 🛠️ Development

Plugin main file: `allpay.php`  
Manifest file: `allpay.xml`

### Structure:
- Signature generation for security (HMAC-SHA256)
- JSON and POST notification support
- Logging using `vmError` and `vmInfo` for diagnostics

---

## 🧪 Testing

To test the integration:

- Place a test order in VirtueMart
- Ensure the Allpay payment URL is generated correctly
- Complete payment on the Allpay sandbox (or real) environment
- Verify order status update in Joomla admin panel

---

## 📬 Support

If you encounter issues or have questions, please contact us at:  
📧 [info@allpay.co.il](mailto:info@allpay.co.il)

---

## 📄 License

**GPLv3 or later**  
(C) 2025 [Allpay.co.il](https://allpay.co.il). All rights reserved.


