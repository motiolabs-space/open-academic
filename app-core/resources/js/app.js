import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

import './stores/ui';
import './components/lembar-nilai';

Alpine.plugin(collapse);

window.Alpine = Alpine;

Alpine.start();
