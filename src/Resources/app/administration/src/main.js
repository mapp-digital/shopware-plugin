import './component/mapp-connection-status';
import './component/mapp-select';

import MappConnectService from './services/mapp-connect.service';

import './module/event-config';

Shopware.Service().register('mappconnect', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new MappConnectService(initContainer.httpClient, container.loginService);
});
