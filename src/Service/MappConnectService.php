<?php declare(strict_types=1);

namespace Mapp\Connect\Shopware\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class MappConnectService
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public const SYSTEM_CONFIG_DOMAIN = 'MappConnect.config.';

    private $isEnable = false;

    protected $client = null;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;

        $this->isEnable = $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . 'integrationEnable');

    }

    function getMappConnectClient()
    {
        if (is_null($this->client)) {
            if ($this->isEnable) {
                $this->client = new Client(
                    $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . 'baseUrl'),
                    $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . 'integrationID'),
                    $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . 'integrationSecret')
                );
            }
        }
        return $this->client;
    }

    public function getConnectionStatus()
    {
        $mc = $this->getMappConnectClient();

        if (is_null($mc))
            return [ 'status' => 'NO' ];

        if (!$mc->ping())
            return [ 'status' => 'ERROR', 'error' => 'Authentication failed' ];

        if (!$this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . 'connectionStatus')) {

            $this->systemConfigService->set(self::SYSTEM_CONFIG_DOMAIN . 'connectionStatus', true);

            $resp = $mc->connect([
              'params' => [
                'shopwareurl' => $_SERVER['APP_URL'],
                'shopwareversion' => \PackageVersions\Versions::getVersion('shopware/core'),
                'shopwarename' => $this->systemConfigService->get('core.basicInformation.shopName'),
                'website' => parse_url($_SERVER['APP_URL'], PHP_URL_HOST)
              ]
            ]);

            if (!is_null($resp['customersGroupId']))
                $this->systemConfigService->set(self::SYSTEM_CONFIG_DOMAIN . 'groupCustomers', $resp['customersGroupId']);
            if (!is_null($resp['subscribersGroupId']))
                $this->systemConfigService->set(self::SYSTEM_CONFIG_DOMAIN . 'groupNewsletter', $resp['subscribersGroupId']);
            if (!is_null($resp['guestsGroupId']))
                $this->systemConfigService->set(self::SYSTEM_CONFIG_DOMAIN . 'groupGuests', $resp['guestsGroupId']);
        }

        return [ 'status' => 'OK' ];

    }

    public function getGroups() {
        $groups = [ [ 'id' => 0, 'name' => '--- No data ---' ] ];
        $mc = $this->getMappConnectClient();
        if (is_null($mc))
            return $groups;

        $groups = [];
        foreach ($mc->getGroups() as $id => $name)
            $groups[] = [ 'id' => $id, 'name' => $name ];

        return $groups;
    }

    public function getMessages() {
        $messages = [ [ 'id' => 0, 'name' => '--- No data ---' ] ];
        $mc = $this->getMappConnectClient();
        if (is_null($mc))
            return $messages;

        $messages = [ [ 'id' => 0, 'name' => '--- Automation ---' ] ];
        foreach ($mc->getMessages() as $id => $name)
            $messages[] = [ 'id' => $id, 'name' => $name ];

        return $messages;
    }

    public function isEnable($data = null) {
        if (is_null($data) && $this->isEnable)
            return true;
        if ($this->isEnable && $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . $data))
            return true;
        return false;
    }

    public function getGroup($group) {
        return $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . 'group' . $group);
    }

}
