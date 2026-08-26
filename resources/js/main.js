/**
 * @file resources/js/main.js
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Entry point for the OMP 3.4 Vue 2 frontend integration
 */

import {initializeThothWorkflow} from './thothWorkflow.mjs';
import {registerVue2Components} from './vue2Components.mjs';
import './thoth.css';

registerVue2Components();
initializeThothWorkflow();
