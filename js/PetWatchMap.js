/**
 * PetWatchMap.js
 *
 * Handles the interactive sightings map for petWatch.
 * Uses two ES6 classes:
 *   - SightingMap  : sets up the Leaflet map, markers and geolocation
 *   - SightingForm : handles the AJAX sighting submission form
 */

'use strict';

// ─────────────────────────────────────────────────────────────
//  SightingMap
//  Responsible for the map itself — loading sightings from the
//  API, placing markers, and handling the sightings list panel.
// ─────────────────────────────────────────────────────────────

class SightingMap {

    constructor(mapElementId, apiBase) {
        this.mapElementId = mapElementId;
        this.apiBase      = apiBase;
        this.map          = null;
        this.markers      = {}; // keyed by sighting id so we can find them later
        this.sightings    = [];
    }

    // Initialise everything — called once on page load
    init() {
        this._setupMap();
        this._centreOnUserLocation();
        this._loadSightings();
        this._bindFilterEvents();
    }

    // Create the Leaflet map and add the OpenStreetMap tile layer
    _setupMap() {
        this.map = L.map(this.mapElementId).setView([53.4808, -2.2426], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(this.map);
    }

    // Try to centre the map on the user's real location using the Geolocation API
    _centreOnUserLocation() {
        const statusEl = document.getElementById('pw-location-status');

        if (!navigator.geolocation) {
            if (statusEl) statusEl.textContent = '📍 Geolocation not supported — showing Manchester';
            return;
        }

        if (statusEl) statusEl.textContent = '📡 Detecting your location…';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                this.map.setView([lat, lng], 14);

                // Add a simple blue circle to mark "you are here"
                L.circleMarker([lat, lng], {
                    radius: 8,
                    color: '#4e6ef2',
                    fillColor: '#4e6ef2',
                    fillOpacity: 0.9
                })
                    .bindPopup('<strong>📍 Your location</strong>')
                    .addTo(this.map);

                if (statusEl) statusEl.textContent = '✅ Location found';
            },
            (error) => {
                console.warn('Geolocation failed:', error.message);
                if (statusEl) statusEl.textContent = '📍 Using default location (Manchester)';
            }
        );
    }

    // Fetch sightings from the API and render them
    _loadSightings(filters = {}) {
        const listEl = document.getElementById('pw-sightings-list');
        if (listEl) listEl.innerHTML = '<li class="pw-list__loading">Loading sightings…</li>';

        // Build query string from any active filters
        const params = new URLSearchParams();
        if (filters.name)    params.set('name',    filters.name);
        if (filters.species) params.set('species', filters.species);
        if (filters.status)  params.set('status',  filters.status);

        const url = this.apiBase + 'sightings.php?' + params.toString();

        fetch(url, { credentials: 'same-origin' })
            .then(response => response.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                this.sightings = data.sightings || [];
                this._clearMarkers();
                this._renderMarkers();
                this._renderList();
            })
            .catch(err => {
                console.error('Failed to load sightings:', err.message);
                if (listEl) listEl.innerHTML = '<li class="pw-list__loading">Failed to load sightings.</li>';
            });
    }

    // Remove all existing markers from the map
    _clearMarkers() {
        Object.values(this.markers).forEach(marker => marker.remove());
        this.markers = {};
    }

    // Place a marker on the map for each sighting
    _renderMarkers() {
        this.sightings.forEach(sighting => {
            const colour = sighting.status === 'lost' ? 'red' : 'green';

            // Use a simple coloured circle marker — easy to understand
            const marker = L.circleMarker([sighting.lat, sighting.lng], {
                radius:      10,
                color:       colour,
                fillColor:   colour,
                fillOpacity: 0.75,
                weight:      2
            });

            marker.bindPopup(this._buildPopupHtml(sighting));

            // Clicking a marker also highlights the matching list item
            marker.on('click', () => {
                this._highlightListItem(sighting.id);
            });

            marker.addTo(this.map);
            this.markers[sighting.id] = marker;
        });
    }

    // Build the HTML string shown inside a marker popup
    _buildPopupHtml(sighting) {
        const statusLabel = sighting.status === 'lost' ? '🔴 Missing' : '🟢 Sighted';
        return `
            <strong>${sighting.pet_name}</strong> (${sighting.species})<br>
            <em>${statusLabel}</em><br><br>
            "${sighting.comment}"<br><br>
            <small>Reported by ${sighting.username}<br>${sighting.timestamp}</small>
        `;
    }

    // Render the sightings as a list in the sidebar
    _renderList() {
        const listEl = document.getElementById('pw-sightings-list');
        const countEl = document.getElementById('pw-sightings-count');

        if (!listEl) return;

        if (this.sightings.length === 0) {
            listEl.innerHTML = '<li class="pw-list__loading">No sightings found.</li>';
            if (countEl) countEl.textContent = '0';
            return;
        }

        listEl.innerHTML = '';

        this.sightings.forEach(sighting => {
            const li = document.createElement('li');
            li.className = 'pw-list__item';
            li.dataset.id = sighting.id;

            li.innerHTML = `
                <div class="pw-list__item-header">
                    <span class="pw-list__pet-name">${sighting.pet_name}</span>
                    <span class="pw-list__status pw-list__status--${sighting.status}">
                        ${sighting.status === 'lost' ? '🔴 Missing' : '🟢 Sighted'}
                    </span>
                </div>
                <div class="pw-list__species">${sighting.species}</div>
                <p class="pw-list__comment">"${sighting.comment}"</p>
                <div class="pw-list__footer">
                    <span>👤 ${sighting.username}</span>
                    <span>${sighting.timestamp}</span>
                </div>
            `;

            // Clicking a list item flies the map to that marker
            li.addEventListener('click', () => {
                this._flyToSighting(sighting);
                this._highlightListItem(sighting.id);
            });

            listEl.appendChild(li);
        });

        if (countEl) countEl.textContent = this.sightings.length;
    }

    // Pan and zoom the map to a specific sighting
    _flyToSighting(sighting) {
        this.map.flyTo([sighting.lat, sighting.lng], 15);
        if (this.markers[sighting.id]) {
            this.markers[sighting.id].openPopup();
        }
    }

    // Highlight the active list item and remove highlight from others
    _highlightListItem(sightingId) {
        document.querySelectorAll('.pw-list__item').forEach(el => {
            el.classList.remove('pw-list__item--active');
        });
        const active = document.querySelector(`.pw-list__item[data-id="${sightingId}"]`);
        if (active) {
            active.classList.add('pw-list__item--active');
            active.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    // Wire up the filter bar inputs to re-fetch sightings on change
    _bindFilterEvents() {
        const filterForm = document.getElementById('pw-filter-bar');
        if (!filterForm) return;

        // Debounce so we don't fire on every keypress
        let debounceTimer;
        const triggerFilter = () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const filters = {
                    name:    filterForm.querySelector('[name="filter_name"]')?.value.trim()    || '',
                    species: filterForm.querySelector('[name="filter_species"]')?.value.trim() || '',
                    status:  filterForm.querySelector('[name="filter_status"]')?.value         || '',
                };
                this._loadSightings(filters);
            }, 400);
        };

        filterForm.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', triggerFilter);
        });
        filterForm.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', triggerFilter);
        });

        // Clear button resets all filters
        const clearBtn = filterForm.querySelector('[data-action="clear-filters"]');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                filterForm.querySelectorAll('input').forEach(i  => i.value  = '');
                filterForm.querySelectorAll('select').forEach(s => s.value = 'lost');
                this._loadSightings();
            });
        }

        // "Fit all" button zooms out to show every marker
        const fitBtn = document.querySelector('[data-action="fit-markers"]');
        if (fitBtn) {
            fitBtn.addEventListener('click', () => {
                if (this.sightings.length === 0) return;
                const bounds = this.sightings.map(s => [s.lat, s.lng]);
                this.map.fitBounds(bounds, { padding: [40, 40] });
            });
        }
    }

    // Allow external code (SightingForm) to add a new marker after submission
    addSighting(sighting) {
        this.sightings.unshift(sighting);
        this._renderMarkers();
        this._renderList();
        this._flyToSighting(sighting);
    }

    // Return the Leaflet map instance so SightingForm can attach click events
    getMap() {
        return this.map;
    }
}


// ─────────────────────────────────────────────────────────────
//  SightingForm
//  Responsible for the "Report a Sighting" form in the sidebar.
//  Only shown to logged-in users. Submits via AJAX.
// ─────────────────────────────────────────────────────────────

class SightingForm {

    constructor(formId, apiBase, csrfToken, sightingMap) {
        this.formEl     = document.getElementById(formId);
        this.apiBase    = apiBase;
        this.csrfToken  = csrfToken;
        this.sightingMap = sightingMap; // reference so we can add the new marker

        if (!this.formEl) return; // not logged in — form won't be in the DOM

        this.latInput   = this.formEl.querySelector('[name="latitude"]');
        this.lngInput   = this.formEl.querySelector('[name="longitude"]');
        this.coordLabel = this.formEl.querySelector('.pw-form__coords-display');
        this.statusDiv  = this.formEl.querySelector('.pw-form__status');
        this.submitBtn  = this.formEl.querySelector('[type="submit"]');

        this._loadPetsList();
        this._enableMapClick();
        this._bindSubmit();
        this._bindToggle();
    }

    // Fetch pets from the API and populate the dropdown
    _loadPetsList() {
        fetch(this.apiBase + 'pets.php', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                const select = this.formEl.querySelector('[name="pet_id"]');
                if (!select || !data.pets) return;

                select.innerHTML = '<option value="">— Select a pet —</option>';
                data.pets.forEach(pet => {
                    const opt = document.createElement('option');
                    opt.value = pet.id;
                    opt.textContent = `${pet.name} (${pet.species})`;
                    select.appendChild(opt);
                });
            })
            .catch(err => console.warn('Could not load pets:', err.message));
    }

    // Let the user click the map to set the sighting location
    _enableMapClick() {
        const map = this.sightingMap.getMap();
        if (!map) return;

        map.on('click', (e) => {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;

            if (this.latInput)   this.latInput.value  = lat.toFixed(6);
            if (this.lngInput)   this.lngInput.value  = lng.toFixed(6);
            if (this.coordLabel) {
                this.coordLabel.textContent = `📍 ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                this.coordLabel.style.color = '#4e9ef2';
            }
        });
    }

    // Handle the form submission via AJAX fetch
    _bindSubmit() {
        this.formEl.addEventListener('submit', (e) => {
            e.preventDefault();

            const petId    = parseInt(this.formEl.querySelector('[name="pet_id"]')?.value) || 0;
            const comment  = this.formEl.querySelector('[name="comment"]')?.value.trim()   || '';
            const lat      = parseFloat(this.latInput?.value)  || null;
            const lng      = parseFloat(this.lngInput?.value) || null;

            // Basic client-side validation before sending
            if (!petId)              return this._showStatus('Please select a pet.', 'error');
            if (comment.length < 5)  return this._showStatus('Comment must be at least 5 characters.', 'error');
            if (!lat || !lng)        return this._showStatus('Please click the map to set a location.', 'error');

            this._setLoading(true);

            fetch(this.apiBase + 'add_sighting.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':  'application/json',
                    'X-CSRF-Token':  this.csrfToken
                },
                body: JSON.stringify({ pet_id: petId, comment, latitude: lat, longitude: lng })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);

                    // Update CSRF token if server rotated it
                    if (data.csrf_token) this.csrfToken = data.csrf_token;

                    this._showStatus(data.message || 'Sighting submitted!', 'success');
                    this.formEl.reset();
                    if (this.coordLabel) {
                        this.coordLabel.textContent = '📍 Click the map to set location';
                        this.coordLabel.style.color = '';
                    }

                    // Add the new marker to the map immediately without a full reload
                    if (data.sighting) {
                        this.sightingMap.addSighting({
                            id:        Date.now(),
                            pet_name:  data.sighting.pet_name,
                            species:   data.sighting.species,
                            status:    data.sighting.status,
                            comment:   data.sighting.comment,
                            username:  'You',
                            timestamp: data.sighting.timestamp,
                            lat:       data.sighting.lat,
                            lng:       data.sighting.lng,
                        });
                    }
                })
                .catch(err => {
                    this._showStatus(err.message, 'error');
                })
                .finally(() => {
                    this._setLoading(false);
                });
        });
    }

    // Toggle the form panel open/closed
    _bindToggle() {
        const toggleBtn = document.getElementById('pw-form-toggle');
        const formBody  = document.getElementById('pw-form-body');
        if (!toggleBtn || !formBody) return;

        toggleBtn.addEventListener('click', () => {
            const isOpen = formBody.classList.toggle('open');
            toggleBtn.classList.toggle('open', isOpen);
        });
    }

    _showStatus(message, type) {
        if (!this.statusDiv) return;
        this.statusDiv.textContent = message;
        this.statusDiv.className   = `pw-form__status pw-form__status--${type}`;
    }

    _setLoading(isLoading) {
        if (!this.submitBtn) return;
        this.submitBtn.disabled    = isLoading;
        this.submitBtn.textContent = isLoading ? 'Submitting…' : 'Submit Sighting';
    }
}


// ─────────────────────────────────────────────────────────────
//  Entry point — called from map.phtml after the DOM is ready
// ─────────────────────────────────────────────────────────────

function initPetWatchMap(config) {
    // 1. Create and initialise the map
    const sightingMap = new SightingMap('pw-map', config.apiBase);
    sightingMap.init();

    // 2. If the user is logged in, set up the sighting submission form
    if (config.isLoggedIn) {
        new SightingForm('pw-add-sighting-form', config.apiBase, config.csrfToken, sightingMap);
    }
}

window.initPetWatchMap = initPetWatchMap;