// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Gestión rápida de «Mis cursos» (vista de tarjetas de block_myoverview).
 *
 * Cada vez que el bloque pinta tarjetas, la plantilla view-cards llama a init(). El módulo:
 *  1. pregunta al servidor (theme_willedtech_course_quickstate) qué puede hacer el usuario en esos cursos;
 *  2. añade el interruptor «Visible / Oculto» y descubre los atajos del menú ⋮ solo donde hay permiso;
 *  3. ofrece filtros rápidos (Todos · Visibles · Ocultos · Favoritos) sobre las tarjetas que hay en pantalla.
 * El servidor vuelve a comprobar el permiso al cambiar la visibilidad: la interfaz nunca es la barrera.
 *
 * Se publica igual en amd/src y amd/build (AMD sin transpilar), porque el tema no usa Grunt.
 *
 * @module     theme_willedtech/quickmanage
 * @copyright  2026 WillEdTech
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str', 'core/notification', 'core/toast'], function(Ajax, Str, Notification, Toast) {
    'use strict';

    var SEL = {
        block: '.block_myoverview',
        deck: '[data-region="card-deck"]',
        card: '.wt-coursecard[data-course-id]',
        quick: '[data-region="wt-quick"]',
        manageMenu: '[data-region="wt-manage-menu"]',
        filter: '[data-region="filter"]',
        favourite: '[data-region="is-favourite"]'
    };

    var STRINGS = [
        'qm_visible', 'qm_hidden', 'qm_switch_aria', 'qm_toast_hidden', 'qm_toast_shown',
        'hidefromstudents', 'showtostudents',
        'filter_label', 'filter_all', 'filter_visible', 'filter_hidden', 'filter_favourites'
    ];

    var listening = false;
    var strings = null;
    // Filtro activo por bloque (id del elemento del bloque → clave).
    var activeFilter = {};
    // Cursos donde el usuario puede cambiar la visibilidad (id → true).
    var manageable = {};

    /**
     * Cadenas del tema, cargadas una vez.
     *
     * @return {Promise<Object>}
     */
    function loadStrings() {
        if (strings) {
            return Promise.resolve(strings);
        }
        return Str.get_strings(STRINGS.map(function(key) {
            return {key: key, component: 'theme_willedtech'};
        })).then(function(values) {
            strings = {};
            STRINGS.forEach(function(key, i) {
                strings[key] = values[i];
            });
            return strings;
        });
    }

    /**
     * Sustituye {$a} en una cadena ya cargada.
     *
     * @param {String} text
     * @param {String} value
     * @return {String}
     */
    function fill(text, value) {
        return text.replace('{$a}', value);
    }

    /**
     * Nombre visible del curso de una tarjeta.
     *
     * @param {HTMLElement} card
     * @return {String}
     */
    function courseName(card) {
        var named = card.querySelector('.coursename [title]');
        return named ? named.getAttribute('title') : '';
    }

    /**
     * Refleja la visibilidad en la tarjeta: clase, sello, interruptor y entrada del menú.
     *
     * @param {HTMLElement} card
     * @param {Boolean} visible
     */
    function paintVisibility(card, visible) {
        card.classList.toggle('wt-coursecard--hidden', !visible);
        card.dataset.wtVisible = visible ? '1' : '0';

        var input = card.querySelector('input[data-action="wt-switch-visibility"]');
        if (input) {
            input.checked = visible;
            var label = card.querySelector('[data-region="wt-switch-label"]');
            if (label) {
                label.textContent = visible ? strings.qm_visible : strings.qm_hidden;
            }
        }
        var item = card.querySelector('[data-action="wt-toggle-visibility"]');
        if (item) {
            item.querySelector('[data-region="wt-vis-label"]').textContent =
                visible ? strings.hidefromstudents : strings.showtostudents;
            var icon = item.querySelector('[data-region="wt-vis-icon"]');
            icon.classList.toggle('fa-eye-slash', visible);
            icon.classList.toggle('fa-eye', !visible);
        }
    }

    /**
     * Interruptor accesible (role=switch) dentro del hueco de gestión rápida.
     *
     * @param {HTMLElement} card
     * @param {Number} courseid
     */
    function addSwitch(card, courseid) {
        var slot = card.querySelector(SEL.quick);
        if (!slot || slot.querySelector('input')) {
            return;
        }
        var id = 'wt-vis-' + courseid + '-' + Math.random().toString(36).slice(2, 8);
        var wrap = document.createElement('div');
        wrap.className = 'form-check form-switch wt-visswitch';

        var input = document.createElement('input');
        input.className = 'form-check-input';
        input.type = 'checkbox';
        input.id = id;
        input.setAttribute('role', 'switch');
        input.setAttribute('data-action', 'wt-switch-visibility');
        input.setAttribute('data-course-id', courseid);
        input.setAttribute('aria-label', fill(strings.qm_switch_aria, courseName(card)));

        var label = document.createElement('label');
        label.className = 'form-check-label';
        label.htmlFor = id;
        label.setAttribute('data-region', 'wt-switch-label');
        label.setAttribute('aria-hidden', 'true');

        wrap.appendChild(input);
        wrap.appendChild(label);
        slot.appendChild(wrap);
    }

    /**
     * Descubre los atajos del menú ⋮ que el servidor autorizó.
     *
     * @param {HTMLElement} card
     * @param {Object} state
     */
    function revealMenu(card, state) {
        var group = card.querySelector(SEL.manageMenu);
        if (!group) {
            return;
        }
        var caps = {visibility: state.canvisibility, update: state.canupdate, participants: state.canparticipants};
        var any = false;
        group.querySelectorAll('[data-wt-cap]').forEach(function(link) {
            var allowed = !!caps[link.getAttribute('data-wt-cap')];
            link.classList.toggle('d-none', !allowed);
            any = any || allowed;
        });
        group.classList.toggle('d-none', !any);
    }

    /**
     * ¿Cumple la tarjeta el filtro?
     *
     * @param {HTMLElement} card
     * @param {String} key
     * @return {Boolean}
     */
    function matches(card, key) {
        switch (key) {
            case 'visible':
                return card.dataset.wtVisible === '1';
            case 'hidden':
                return card.dataset.wtVisible === '0';
            case 'favourites':
                var star = card.querySelector(SEL.favourite);
                return !!star && !star.classList.contains('hidden');
            default:
                return true;
        }
    }

    /**
     * Aplica el filtro activo del bloque y actualiza los contadores de los chips.
     *
     * @param {HTMLElement} block
     */
    function applyFilter(block) {
        var bar = block.querySelector('.wt-quickfilter');
        if (!bar) {
            return;
        }
        var key = activeFilter[block.id] || 'all';
        var cards = Array.prototype.slice.call(block.querySelectorAll(SEL.deck + ' ' + SEL.card));
        cards.forEach(function(card) {
            var cell = card.closest('.col') || card;
            cell.classList.toggle('d-none', !matches(card, key));
        });
        bar.querySelectorAll('[data-wt-filter]').forEach(function(chip) {
            var chipkey = chip.getAttribute('data-wt-filter');
            var count = cards.filter(function(card) {
                return matches(card, chipkey);
            }).length;
            chip.querySelector('.wt-quickfilter-count').textContent = count;
            chip.setAttribute('aria-pressed', chipkey === key ? 'true' : 'false');
        });
    }

    /**
     * Barra de chips, una por bloque, colocada bajo los controles de Moodle (que no se re-renderizan).
     *
     * @param {HTMLElement} block
     */
    function ensureFilterBar(block) {
        if (block.querySelector('.wt-quickfilter')) {
            return;
        }
        var anchor = block.querySelector(SEL.filter);
        if (!anchor) {
            return;
        }
        var bar = document.createElement('div');
        bar.className = 'wt-quickfilter';
        bar.setAttribute('role', 'group');
        bar.setAttribute('aria-label', strings.filter_label);
        [['all', strings.filter_all], ['visible', strings.filter_visible],
            ['hidden', strings.filter_hidden], ['favourites', strings.filter_favourites]].forEach(function(def) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'wt-quickfilter-chip';
            chip.setAttribute('data-wt-filter', def[0]);
            chip.setAttribute('aria-pressed', 'false');
            var text = document.createElement('span');
            text.textContent = def[1];
            var count = document.createElement('span');
            count.className = 'wt-quickfilter-count';
            chip.appendChild(text);
            chip.appendChild(count);
            bar.appendChild(chip);
        });
        anchor.insertAdjacentElement('afterend', bar);
    }

    /**
     * Procesa las tarjetas nuevas de todos los bloques de la página.
     *
     * @return {Promise}
     */
    function scan() {
        var fresh = Array.prototype.slice.call(document.querySelectorAll(SEL.block + ' ' + SEL.deck + ' ' + SEL.card))
            .filter(function(card) {
                return !card.dataset.wtQm;
            });
        if (!fresh.length) {
            return Promise.resolve();
        }
        fresh.forEach(function(card) {
            card.dataset.wtQm = '1';
        });
        var ids = fresh.map(function(card) {
            return parseInt(card.getAttribute('data-course-id'), 10);
        });

        return loadStrings().then(function() {
            return Ajax.call([{methodname: 'theme_willedtech_course_quickstate', args: {courseids: ids}}])[0];
        }).then(function(states) {
            var byid = {};
            states.forEach(function(state) {
                byid[state.id] = state;
            });
            fresh.forEach(function(card) {
                var id = parseInt(card.getAttribute('data-course-id'), 10);
                var state = byid[id];
                if (!state) {
                    return;
                }
                if (state.canvisibility) {
                    manageable[id] = true;
                    addSwitch(card, id);
                }
                revealMenu(card, state);
                paintVisibility(card, state.visible);
            });
            document.querySelectorAll(SEL.block).forEach(function(block) {
                var cards = block.querySelectorAll(SEL.deck + ' ' + SEL.card);
                var manages = Array.prototype.some.call(cards, function(card) {
                    return manageable[card.getAttribute('data-course-id')];
                });
                if (manages) {
                    ensureFilterBar(block);
                }
                applyFilter(block);
            });
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Cambia la visibilidad en el servidor y repinta la tarjeta. Si falla, deja todo como estaba.
     *
     * @param {HTMLElement} card
     * @param {Boolean} visible
     */
    function setVisibility(card, visible) {
        var id = parseInt(card.getAttribute('data-course-id'), 10);
        var input = card.querySelector('input[data-action="wt-switch-visibility"]');
        var before = card.dataset.wtVisible === '1';
        if (input) {
            input.disabled = true;
        }
        card.classList.add('wt-coursecard--busy');
        paintVisibility(card, visible);

        Ajax.call([{methodname: 'theme_willedtech_set_course_visibility', args: {courseid: id, visible: visible}}])[0]
            .then(function(result) {
                paintVisibility(card, result.visible);
                Toast.add(fill(result.visible ? strings.qm_toast_shown : strings.qm_toast_hidden, courseName(card)));
                return null;
            })
            .catch(function(error) {
                paintVisibility(card, before);
                Notification.exception(error);
            })
            .then(function() {
                if (input) {
                    input.disabled = false;
                }
                card.classList.remove('wt-coursecard--busy');
                var block = card.closest(SEL.block);
                if (block) {
                    applyFilter(block);
                }
                return null;
            });
    }

    /**
     * Escuchas delegadas en el documento: sobreviven a cada re-render del bloque.
     */
    function listen() {
        if (listening) {
            return;
        }
        listening = true;

        document.addEventListener('change', function(e) {
            var input = e.target.closest('input[data-action="wt-switch-visibility"]');
            if (input) {
                setVisibility(input.closest(SEL.card), input.checked);
            }
        });

        document.addEventListener('click', function(e) {
            var item = e.target.closest('[data-action="wt-toggle-visibility"]');
            if (item) {
                e.preventDefault();
                var card = item.closest(SEL.card);
                setVisibility(card, card.dataset.wtVisible !== '1');
                return;
            }
            var chip = e.target.closest('.wt-quickfilter [data-wt-filter]');
            if (chip) {
                var block = chip.closest(SEL.block);
                activeFilter[block.id] = chip.getAttribute('data-wt-filter');
                applyFilter(block);
                return;
            }
            // Favorito marcado o quitado desde el menú de Moodle: los contadores cambian.
            if (e.target.closest('[data-action="add-favourite"], [data-action="remove-favourite"]')) {
                var fblock = e.target.closest(SEL.block);
                setTimeout(function() {
                    applyFilter(fblock);
                }, 400);
            }
        });
    }

    return {
        /**
         * Punto de entrada; idempotente, se llama tras cada render de la vista de tarjetas.
         */
        init: function() {
            listen();
            scan();
        }
    };
});
