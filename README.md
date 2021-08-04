# Mapp Connect APIs Client Library for PHP #

## Overall information ##

*shopware-plugin* project is a middleware between *MappConnect* service and *Shopware*
to execute basic events:

* connection status
* get messages
* get groups
* execute specified events for given *integrationId*

## Installation ##

1. For *Shopware plugin* you should put plugin sources to Shopware `src/custom/plugins/` folder cleaning `shopware-plugin` names/folders first, 
app will automatically detect all plugins that residue within this folder,

2. If upgrading from previous version please remember to update database
`bin/console database:migrate MappConnect --all`

Clear shopware cache
`bin/console cache:clear`

And deploy new UI assets
`bin/console asset:install`

3. *php-client* needs to be installed within *Shopware & Magento plugin*
* The project needs at least of php version 7.0* 

4. In the *Shopware plugin* you should first get *php-client* which you can install within *plugin* folder (in Shopware 6 you should go to path `/src/custom/plugins/[shopware_plugin_name]`), and then run `composer install`,

5. *Shopware plugin* you can activate by 

5a. Going to *Administration* section in Shopware, and next go to *Settings->System->Plugins* and from the list **Install** ( you can also **Configure** it from context menu), and next **Activate** this within toggle button,

5b. You can also activate this from the Shopware console by:

* `php bin/console plugin:refresh`
* `php bin/console plugin:install --activate MappConnect`

And you can now use Plugin along with all events support in relation to Mapp Connect.
