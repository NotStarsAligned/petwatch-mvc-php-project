/**
 * PetWatchMap.js
 *
 * Interactive live sightings map
 * All AJAX calls go through a single endpoint: api/ajax.php?cmd=...
 *
 * Classes:
 *   GeoLocation  — encapsulates geolocation
 *   SightingMap  — map setup, markers, filter wiring, list-to-map focus
 *   SightingForm — add-sighting form injected into marker popups
 */

'use strict';


//  GeoLocation
//  Wraps the browser Geolocation API in a reusable class.
//  Keeps location logic separate from the map

class GeoLocation {

    constructor() {

        this.available = ("geolocation" in navigator);
    }

    // Get the user's current position.
    getCurrentPosition(onSuccess, onError) {
        if (!this.available) {
            if (onError) onError({ code: 0, message: 'Geolocation is not supported by this browser.' });
            return;
        }

        // Named success callback
        function success(position) {
            onSuccess(position.coords.latitude, position.coords.longitude);
        }

        // Named error callback with code + message
        function error(err) {
            const messages = {
                1: 'Location access was denied by the user.',
                2: 'Location information is unavailable.',
                3: 'Location request timed out.'
            };
            if (onError) onError({ code: err.code, message: messages[err.code] || err.message });
        }

        navigator.geolocation.getCurrentPosition(success, error);
    }
}



//  SightingMap
//  Manages the Leaflet map — setup, markers, filters, and
//  the list-to-map focus feature (clicking a card flies the map).


class SightingMap {

    constructor(mapElementId, apiBase) {
        this.mapElementId = mapElementId;
        this.apiBase = apiBase; // path to api/ folder
        this.map = null;
        this.markers = {}; // sighting id -> Leaflet circleMarker
        this.sightings = [];

        this.geoLocation = new GeoLocation();
    }

    // Bootstrap everything — called once on page load
    init() {
        this._setupMap();
        this._centreOnUserLocation();
        this._loadMarkers();
        this._bindFilterEvents();
        this._bindCardFocusEvents();
    }

    // Map setup

    // Create the Leaflet map centred on Manchester as a fallback.
    // Geolocation will move it once the user's position is known.
    _setupMap() {
        this.map = L.map(this.mapElementId).setView([53.4808, -2.2426], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(this.map);
    }

    // Geolocation

    // Centre the map on the user's real location
    _centreOnUserLocation() {
        const statusEl = document.getElementById('pw-location-status');

        if (!this.geoLocation.available) {
            if (statusEl) statusEl.textContent = 'Location unavailable — showing Manchester';
            return;
        }

        if (statusEl) statusEl.textContent = 'Detecting your location…';

        this.geoLocation.getCurrentPosition(
            (lat, lng) => this._onLocationSuccess(lat, lng, statusEl),
            (err)       => this._onLocationError(err, statusEl)
        );
    }

    _onLocationSuccess(lat, lng, statusEl) {
        this.map.setView([lat, lng], 14);

        // Blue dot for "you are here"
        L.circleMarker([lat, lng], {
            radius: 8, color: '#4e6ef2', fillColor: '#4e6ef2', fillOpacity: 0.9
        })
            .bindPopup('<strong>Your location</strong>')
            .addTo(this.map);

        if (statusEl) statusEl.textContent = 'Showing your location';
    }

    // Log error with code and message
    _onLocationError(err, statusEl) {
        console.warn('Geolocation error (' + err.code + '): ' + err.message);
        if (statusEl) statusEl.textContent = 'Location unavailable — showing Manchester';
    }

    //  AJAX marker loading

    // Fetch sightings via ajax.php?cmd=getdata and render markers.
    // Accepts optional filters from the filter bar.
    _loadMarkers(filters = {}) {
        const params = new URLSearchParams({ cmd: 'getdata' });
        if (filters.name) params.set('name',    filters.name);
        if (filters.species) params.set('species', filters.species);
        if (filters.status && filters.status !== 'All') params.set('status',  filters.status);

        fetch(this.apiBase + 'ajax.php?' + params.toString(), {
            credentials: 'same-origin'
        })
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                this.sightings = data.sightings || [];
                this._clearMarkers();
                this._renderMarkers();
            })
            .catch(err => console.error('Failed to load markers:', err.message));
    }

    _clearMarkers() {
        Object.values(this.markers).forEach(m => m.remove());
        this.markers = {};
    }

    // Place a circle marker for each sighting — red = lost, green = found
    _renderMarkers() {
        this.sightings.forEach(sighting => {
            const isLost = sighting.status === 'lost';

            const marker = L.circleMarker([sighting.lat, sighting.lng], {
                radius:      10,
                color:       isLost ? '#e74c3c' : '#2ecc71',
                fillColor:   isLost ? '#e74c3c' : '#2ecc71',
                fillOpacity: 0.8,
                weight:      2
            });

            // Popup includes a placeholder div for the sighting form (logged-in users)
            const popup = L.popup({ maxWidth: 320, minWidth: 260 });
            popup.setContent(this._buildPopupHtml(sighting));
            marker.bindPopup(popup);

            // Inject the sighting form when the popup opens
            marker.on('popupopen', () => {
                if (window._petWatchForm) {
                    window._petWatchForm.bindPopupForm(sighting, marker);
                }
            });

            marker.addTo(this.map);
            this.markers[sighting.id] = marker;
        });
    }

    // Build the info shown inside each marker popup
    _buildPopupHtml(sighting) {
        const status = sighting.status === 'lost' ? '🔴 Missing' : '🟢 Sighted';
        return `
            <strong>${sighting.pet_name}</strong> (${sighting.species})<br>
            <em>${status}</em><br><br>
            "${sighting.comment}"<br><br>
            <small>
                Reported by <strong>${sighting.username}</strong><br>
                ${sighting.timestamp} 
            </small>
            <div id="popup-form-${sighting.id}"></div>
        `;
    }

    // List-to-map focus

    // Fly the map to a sighting's marker and open its popup.
    // Called when the user clicks a sighting card in the list below.
    focusMarker(sightingId) {
        const marker = this.markers[sightingId];
        if (!marker) return;

        // Smooth pan and zoom to the marker location
        this.map.flyTo(marker.getLatLng(), 16, { duration: 0.8 });
        marker.openPopup();
    }

    // Wire up sighting cards in the list below the map.
    // Each card has data-sighting-id set in the PHP view.
    // Clicking it calls focusMarker() to fly the map there.
    _bindCardFocusEvents() {
        // Use event delegation on the listings container
        // works even if cards are re-rendered by live search later
        const listEl = document.getElementById('sighting-listings');
        if (!listEl) return;

        listEl.addEventListener('click', (e) => {
            const card = e.target.closest('[data-sighting-id]');
            if (!card) return;

            const sightingId = parseInt(card.dataset.sightingId);
            if (sightingId) this.focusMarker(sightingId);
        });
    }

    // Filter bar

    // Listen for changes to the filter bar and re-fetch markers.
    // Debounced so API is not called on every single keypress.
    _bindFilterEvents() {
        const filterForm = document.getElementById('sighting-search');
        if (!filterForm) return;

        let debounceTimer;

        const triggerFilter = () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const filters = {
                    name:    filterForm.querySelector('[name="search_name"]')?.value.trim()  || '',
                    species: filterForm.querySelector('[name="search_type"]')?.value.trim()  || '',
                    status:  filterForm.querySelector('[name="search_status"]')?.value       || 'All',
                };
                this._loadMarkers(filters);
            }, 400);
        };

        filterForm.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', triggerFilter);
        });
        filterForm.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', triggerFilter);
        });
    }
}


//  SightingForm
//  Injects a "report a sighting" form into marker popups.
//  Only active for logged-in users.

class SightingForm {

    constructor(apiBase, csrfToken) {
        this.apiBase   = apiBase;
        this.csrfToken = csrfToken;
        this.pets      = []; // cached from ajax.php?cmd=getpets

        this._loadPets();
    }

    // Fetch pet list once via ajax.php?cmd=getpets and cache it.
    // Avoids re-fetching every time a popup opens.
    _loadPets() {
        fetch(this.apiBase + 'ajax.php?cmd=getpets', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                this.pets = data.pets || [];
            })
            .catch(err => console.warn('Could not load pets list:', err.message));
    }

    // Called when a marker popup opens.
    // Injects the form HTML into the placeholder div inside the popup.
    bindPopupForm(sighting, marker) {
        const container = document.getElementById('popup-form-' + sighting.id);
        if (!container) return;

        container.innerHTML = this._buildFormHtml(sighting);

        const form = container.querySelector('.pw-sighting-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this._handleSubmit(form, sighting, marker);
            });
        }
    }

    // Build form HTML — pre-selects the pet matching this sighting
    _buildFormHtml(sighting) {
        const options = this.pets.map(pet => {
            const selected = pet.name === sighting.pet_name ? 'selected' : '';
            return `<option value="${pet.id}" ${selected}>${pet.name} (${pet.species})</option>`;
        }).join('');

        return `
            <hr style="margin:10px 0">
            <p style="margin:0 0 6px; font-weight:600; font-size:0.85rem;">Report a sighting</p>
            <form class="pw-sighting-form">
                <select name="pet_id"
                    style="width:100%; margin-bottom:6px; padding:4px; font-size:0.8rem;">
                    <option value="">— Select pet —</option>
                    ${options}
                </select>
                <textarea name="comment"
                    placeholder='e.g. "Spotted near Piccadilly Gardens heading north…"'
                    rows="3" maxlength="500"
                    style="width:100%; padding:4px; font-size:0.8rem; resize:vertical; box-sizing:border-box;"
                    required></textarea>
                <div class="pw-form-status" style="font-size:0.8rem; margin-bottom:6px;"></div>
                <button type="submit"
                    style="width:100%; padding:5px; background:#198754; color:#fff;
                           border:none; border-radius:4px; cursor:pointer; font-size:0.85rem;">
                    Submit Sighting
                </button>
            </form>
        `;
    }

    // Submit via ajax.php?cmd=add with CSRF token in header
    _handleSubmit(form, sighting, marker) {
        const petId     = parseInt(form.querySelector('[name="pet_id"]')?.value) || 0;
        const comment   = form.querySelector('[name="comment"]')?.value.trim()  || '';
        const statusDiv = form.querySelector('.pw-form-status');

        // Client-side validation before hitting the server
        if (!petId)             return this._showStatus(statusDiv, 'Please select a pet.',                   'error');
        if (comment.length < 5) return this._showStatus(statusDiv, 'Comment must be at least 5 characters.', 'error');

        const latlng    = marker.getLatLng();
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled    = true;
        submitBtn.textContent = 'Submitting…';

        // POST to the single ajax.php endpoint using cmd=add
        fetch(this.apiBase + 'ajax.php?cmd=add', {
            method:      'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken  // CSRF token in header — not a cookie
            },
            body: JSON.stringify({
                pet_id:    petId,
                comment:   comment,
                latitude:  latlng.lat,
                longitude: latlng.lng
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);

                // Server rotates the token after each write — keep ours in sync
                if (data.csrf_token) this.csrfToken = data.csrf_token;

                // Replace the form with a confirmation message
                form.parentElement.innerHTML = `
                <hr style="margin:10px 0">
                <p style="color:#198754; font-size:0.85rem; margin:0;">
                    ✅ Sighting submitted! Thank you.
                </p>`;
            })
            .catch(err => {
                this._showStatus(statusDiv, err.message, 'error');
                submitBtn.disabled    = false;
                submitBtn.textContent = 'Submit Sighting';
            });
    }

    _showStatus(el, message, type) {
        if (!el) return;
        el.textContent = message;
        el.style.color = type === 'error' ? '#dc3545' : '#198754';
    }
}

//  Entry point — called from index.phtml once DOM is ready
function initPetWatchMap(config) {
    // Create and start the map — exposed on window so live search
    // can call focusMarker() when a search result is clicked
    window._petWatchMap = new SightingMap('pw-map', config.apiBase);
    window._petWatchMap.init();

    // Sighting form only created for logged-in users
    if (config.isLoggedIn) {
        window._petWatchForm = new SightingForm(config.apiBase, config.csrfToken);
    }
}

window.initPetWatchMap = initPetWatchMap;