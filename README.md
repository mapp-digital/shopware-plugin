# Mapp Connect Shopware 6 plugin - events extension for Shopware #

## Overall information ##

*shopware-plugin* project is a middleware between *MappConnect* service and *Shopware*
to execute basic events:

* connection status
* get messages
* get groups
* execute specified events for given *integrationId*

## Installation ##

The preferred method is via [composer](https://getcomposer.org). Follow the
[installation instructions](https://getcomposer.org/doc/00-intro.md) if you do not already have
composer installed.

Once composer is installed, execute the following command in your project root to install this library:

```bash
composer require mappconnect/shopware-plugin
php bin/console plugin:refresh
php bin/console plugin:install --activate --clearCache MappConnect
```

# Manual Installation

Alternatively you can download the package in its entirety.
1. Put plugin sources to Shopware `src/custom/plugins/` folder cleaning `shopware-plugin` names/folders first, app will automatically detect all plugins that residue within this folder,

2.
```bash
composer require mappconnect/client
php bin/console plugin:refresh
php bin/console plugin:install --activate --clearCache MappConnect
```
# Configuration

*Shopware plugin* you can activate by

* [Shopware 6.3.x] Going to *Administration* section in Shopware, and next go to *Settings->System->Plugins* and from the list **Install** ( you can also **Configure** it from context menu), and next **Activate** this within toggle button,

* [Shopware 6.4.x] Going to *Extensions* section in Shopware, and next go to *My extensions->Apps* and from the list **Install** ( you can also **Configure** it from context menu), and next **Activate** this within toggle button,

Now you use Plugin along with Buissnes Events triggering message sendout or sending event to Whiteboard (automation).
