const { Application, Classes } = Shopware;
const { ApiService } = Classes;

class FibOutboxActionApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'fib-outbox') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'fibOutboxActionService';
    }

    dispatch(limit = 100) {
        return this.httpClient.post(`/_action/${this.apiEndpoint}/dispatch`, { limit }, {
            headers: this.getBasicHeaders(),
        }).then((response) => ApiService.handleResponse(response));
    }

    resetStuck() {
        return this.httpClient.post(`/_action/${this.apiEndpoint}/reset-stuck`, {}, {
            headers: this.getBasicHeaders(),
        }).then((response) => ApiService.handleResponse(response));
    }

    requeueDead(limit = 100, eventName = null) {
        const payload = { limit };

        if (eventName) {
            payload.eventName = eventName;
        }

        return this.httpClient.post(`/_action/${this.apiEndpoint}/requeue-dead`, payload, {
            headers: this.getBasicHeaders(),
        }).then((response) => ApiService.handleResponse(response));
    }

    getDestinationTypes() {
        return this.httpClient.get(`/_action/${this.apiEndpoint}/destination-types`, {
            headers: this.getBasicHeaders(),
        }).then((response) => ApiService.handleResponse(response));
    }
}

Shopware.Service().register('fibOutboxActionService', () => new FibOutboxActionApiService(
    Application.getContainer('init').httpClient,
    Shopware.Service().get('loginService'),
));

export default FibOutboxActionApiService;
