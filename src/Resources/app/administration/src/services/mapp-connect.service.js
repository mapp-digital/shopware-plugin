const ApiService = Shopware.Classes.ApiService;

class MappConnectService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'mappconnect') {
        super(httpClient, loginService, apiEndpoint);
    }

    getConnectionStatus() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`${this.getApiBasePath()}/connection-status`, { headers: headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getGroups() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`${this.getApiBasePath()}/groups`, { headers: headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getMessages() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`${this.getApiBasePath()}/messages`, { headers: headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default MappConnectService;
