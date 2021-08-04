import './page/mapp-event-config-list';
import './page/mapp-event-config-detail';

Shopware.Module.register('mapp-event-config', {
  type: 'plugin',
  name: 'mapp-event-config',
  title: 'Mapp Connect Events',
  description: '',
  version: '1.0.0',
  targetVersion: '1.0.0',
  color: '#9AA8B5',
  icon: 'default-action-settings',
  favicon: 'icon-module-settings.png',
  entity: 'mapp_event',

  routes: {
    index: {
        component: 'mapp-event-config-list',
        path: 'index',
    },
    detail: {
        component: 'mapp-event-config-detail',
        path: 'detail/:id',
        props: {
            default: (route) => ({ mappEventId: route.params.id })
        },
        meta: {
          parentPath: 'mapp.event.config.index',
        }
    },
    create: {
        component: 'mapp-event-config-detail',
        path: 'create',
        meta: {
          parentPath: 'mapp.event.config.index',
        }
    }
},


settingsItem: [{ // this can be a single object if no collection is needed
  to: 'mapp.event.config.index', // route to anything
  group: 'plugins', // either system, shop or plugins
  icon: 'default-action-settings'
}]

});
