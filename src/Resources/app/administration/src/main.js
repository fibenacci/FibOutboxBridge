const { Locale } = Shopware;

import './service/fib-outbox-action.api.service';
import './module/fib-outbox-bridge';
import './flow/register-flow-actions';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Locale.extend('de-DE', deDE);
Locale.extend('en-GB', enGB);
