# AltaPay PrestaShop Plugin

AltaPay, headquartered in Denmark, is an internationally focused fintech company within payments with the mission to make payments less complicated. We help our merchants grow and expand their business across payment channels by offering a fully integrated seamless omni-channel experience for online, mobile and instore payments, creating transparency and reducing the need for manual tasks with one centralized payment platform.

AltaPay’s platform automizes, simplifies, and protects the transaction flow for shop owners and global retail and e-commerce companies, supporting and integrating smoothly into the major ERP systems. AltaPay performs as a Payment Service Provider operating under The Payment Card Industry Data Security Standard (PCI DSS).

# PrestaShop Payment plugin installation guide

Installing this plug-in will enable the web shop to handle card transactions through AltaPay's gateway.

**Table of Contents**

[Prerequisites](#prerequisites)

[Installation](#installation)

[Configuration](#configuration)

[Troubleshooting](#troubleshooting)

# Prerequisites

Before configuring the plugin, you need the below information. These can
be provided by AltaPay.

1.  AltaPay credentials:

-   Username

-   Password

2.  AltaPay gateway information:

-   Terminal

-   Gateway
-   
# Installation

1. AltaPay only supports PrestaShop version 1.6.x
Go to ‘Modules and Services’ > ‘Modules And Services’ and click on “Add a new module” from the top-right corner.

![add_new_module](https://github.com/AltaPay/plugin-prestashop/blob/Documentation/Docs/Installation/Installing%20the%20PrestaShop.png)

2. Now click on “Choose a file” from the “Add a new module” tab and find the AltaPay.zip file. When you chose the file, click on “Upload the module”.

![upload_module](https://github.com/AltaPay/plugin-prestashop/blob/Documentation/Docs/Installation/Installing%20the%20PrestaShop_1.png)

3. The module is now successfully imported. To finalize the installation, find the module in the list and click on “Install” to the right of the module.

![install_module](https://github.com/AltaPay/plugin-prestashop/blob/Documentation/Docs/Installation/Installing%20the%20PrestaShop_2.png)

4. A window will open - click on “Proceed with the installation”. When it’s done, a green bar will be visible and state that the module has been successfully installed.

![installed_successfully](https://github.com/AltaPay/plugin-prestashop/blob/Documentation/Docs/Installation/Installing%20the%20PrestaShop_3.png)

# Configuration

1. Go to ‘Modules and Services’ > ‘Modules and Services’ and find the AltaPay PrestaShop module. This can be done by searching for ‘AltaPay’ or go to ‘Payment and Gateways’ and find the module. 
2. Click on ‘Configure’ for the module. 
3. Now you can enter the credentials for the API user and URL. The default value for the ‘Callback IP address’ doesn’t need to be changed.

![enter_credentials](https://github.com/AltaPay/plugin-prestashop/blob/Documentation/Docs/Configuration/Configuring%20the%20PrestaShop.png)

4. Now, set up the terminals. At the bottom of the module configuration page, you will find a list of “Terminals”. There is a plus sign which you would need to click, to add a new terminal.

![set_up_terminals](https://github.com/AltaPay/plugin-prestashop/blob/Documentation/Docs/Configuration/Configuring%20the%20PrestaShop.jpg)

5. When setting up a terminal you must select the icon and name that is going to be shown in the check flow.  The currency must correspond with the currency on the terminal at AltaPay.  The payment type indicates if the money would be captured on reservation (‘Authorize and capture’) or the merchant would have to capture, when delivering the goods.

![terminal_config](https://github.com/AltaPay/plugin-prestashop/blob/Documentation/Docs/Configuration/Configuring%20the%20PrestaShop_1.png)

6. When you have set up your terminals you are ready to process transactions through AltaPay.

![verify_terminals](https://github.com/AltaPay/plugin-prestashop/blob/Documentation/Docs/Configuration/Configuring%20the%20PrestaShop_1.jpg)

![process_transactions](https://github.com/AltaPay/plugin-prestashop/blob/Documentation/Docs/Configuration/Configuring%20the%20PrestaShop_2.jpg)


# Troubleshooting

**PHP Warning: Input variables exceeded 1000. To increase the limit change max_input_vars in php.ini.**
> For orders that contain too many products, this PHP warning may be issued. You will have to edit your php.ini file and restart your server.
> The variable that you must change is called max_input_vars. This is the maximum number of variables that can be sent in a request. You can change it, for example, to 3000. The default is 1000.

**Parameters: description/unitPrice/quantity are required for each orderline, but was not set for line: xxxx**
> The same problem as above. The request is being truncated because the number of variables are exceeding the max_input_vars limit.


## Providing error logs to support team

You are always welcome to contact AltaPay support (support@altapay.com) if you are experiencing difficulties.

**In your Prestashop system, the ‘Transaction ID’ is the ID which matches the ‘Order ID’ within the AltaPay backend. Please do not use the Prestashop Order ID as reference ID when talking to AltaPay support.**

It is recommended to provide as much information as possible to support team.

**You can find the CMS logs by following the below steps:**

From Admin Dashboard navigate to **"Advanced Parameters > Logs"** 

**Web server error logs**

**For Apache server** You can find it on **/var/log/apache2/error.log** 
 
**For Nginx** it would be **/var/log/nginx/error.log** 

**_Note: Your path may vary from the mentioned above._**


