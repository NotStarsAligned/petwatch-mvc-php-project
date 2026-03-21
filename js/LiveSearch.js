/**
 * LiveSearch.js
 *
 * Live AJAX search - As the user types, the results update without needing to reload the page
 * - XMLHttpRequest with onreadystatechange
 * - JSON.parse and forEach to render results
 * - Token security on the search endpoint
 */

'use strict' ;

class LiveSearch {

    /**
     * @param {string} apiBase   - Path to the api/ folder
     * @param {string} ajaxToken - Security token from PHP session
     */
    constructor(apiBase, ajaxToken) {
        this.apiBase = apiBase;
        this.ajaxToken = ajaxToken;

        // The existing DOM elements on index.php
        this.nameInput = document.getElementById('search_name');
        this.speciesInput = document.getElementById('search_type');
        this.statusSelect = document.getElementById('search_status');
        this.cardGrid = document.querySelector('#sighting-listings .row');
        this.countText = document.querySelector('.mb-3.text-muted');
        this.pagination = document.querySelector('nav[aria-label="Page navigation"]');

        // Track in-flight XHR so we can abort stale requests
        this.activeXhr = null;
        this.debounceTimer = null;

        this.isLiveMode = false;

        if (!this.nameInput || !this.cardGrid) {
            console.warn('LiveSearch: required elements not found.');
            return;
        }

        this._bindEvents();
    }

    // Event binding

    _bindEvents() {
        // Debounced keyup on text inputs
        // "UI text control which triggers events when changed"
        this.nameInput.addEventListener('keyup',()=>this._onFilterChange());
        this.speciesInput.addEventListener('keyup',() => this._onFilterChange());

        // Instant response on select change
        this.statusSelect.addEventListener('change',() => this._onFilterChange());

        // When the Clear link is clicked, restore PHP-rendered results
        const clearLink = document.querySelector('a[href="index.php"]');
        if (clearLink) {
            clearLink.addEventListener('click', () => {
                // Let the normal page load happen - don't intercept
                this._exitLiveMode();
            });
        }
    }

    _onFilterChange() {
        clearTimeout(this.debounceTimer);

        const name = this.nameInput.value.trim();
        const species = this.speciesInput.value.trim();
        const status = this.statusSelect.value;

        // If all fields are empty, restore the original PHP-rendered cards
        if (name === '' && species === '' && status === 'All') {
            if (this.isLiveMode) {
                this.debounceTimer = setTimeout(() => {
                    this._search('', '', '');
                }, 350);
            }
            return;
        }

        // Debounce - wait 350ms after last keypress before firing
        // Prevents hammering the server on every keystroke
        this.debounceTimer = setTimeout(() => {
            this._search(name, species, status);
        }, 350);
    }

    // AJAX search using XMLHttpRequest

    _search(name, species, status) {

        // Abort any previous in-flight request - prevents stale results
        // overwriting fresh ones and saves memory on fast typing
        if (this.activeXhr) {
            this.activeXhr.abort();
        }

        // Build URL:

        var url = this.apiBase + 'ajax.php'
            + '?cmd=search'
            + '&name=' + encodeURIComponent(name)
            + '&species=' + encodeURIComponent(species)
            + '&status=' + encodeURIComponent(status === 'All' ? '' : status)
            + '&limit=50'
            + '&token=' + encodeURIComponent(this.ajaxToken);

        this._showLoading();


        var xmlhttp = new XMLHttpRequest();
        this.activeXhr = xmlhttp;

        xmlhttp.onreadystatechange = () => {
            if (xmlhttp.readyState == 4) {
                if (xmlhttp.status == 200) {
                    // JSON.parse the response
                    var data = JSON.parse(xmlhttp.responseText);
                    this._renderCards(data);
                } else if (xmlhttp.status !== 0) {
                    // status 0 means the request was aborted - not an error
                    this._showError('Search failed. Please try again.');
                }
                this.activeXhr = null;
            }
        };

        xmlhttp.open('GET', url, true);
        xmlhttp.send();
    }

    // Card rendering

    // Replace the card grid with AJAX results
    // server returns full JSON objects with multiple fields, rendered as
    // rich cards matching the existing Bootstrap card style on the page.
    _renderCards(data) {
        if (data.error) {
            this._showError(data.error);
            return;
        }

        var sightings = data.sightings || [];

        // Update the count text to match what PHP normally renders
        if (this.countText) {
            this.countText.textContent = 'Displaying ' + sightings.length
                + ' of ' + (data.total || sightings.length) + ' total results.';
        }

        // Hide pagination - live search shows all results at once
        if (this.pagination) this.pagination.style.display = 'none';

        if (sightings.length === 0) {
            this.cardGrid.innerHTML =
                '<p class="text-center">No sightings matching your criteria have been reported yet.</p>';
            return;
        }

        // Build all cards using DocumentFragment for memory efficiency -
        // avoids repeated reflows from appending one card at a time
        var fragment = document.createDocumentFragment();

        sightings.forEach((sighting) => {
            var col = this._buildCard(sighting);
            fragment.appendChild(col);
        });

        this.cardGrid.innerHTML = '';
        this.cardGrid.appendChild(fragment);

        // Wire up click-to-focus-map on the newly rendered cards
        this._bindCardClicks();

        this.isLiveMode = true;
    }

    // Build a single card matching the exact Bootstrap markup from index.phtml
    _buildCard(sighting) {
        var isLost = sighting.status === 'lost';
        var statusClass= isLost ? 'bg-danger-subtle border-danger' : 'bg-success-subtle border-success';
        var textColor= isLost ? 'text-danger' : 'text-success';
        var statusLabel= isLost ? 'lost' : 'found';

        var col = document.createElement('div');
        col.className = 'col-md-6 col-lg-4 mb-4';

        var card = document.createElement('div');
        card.className = 'card h-100 ' + statusClass + ' border-start border-4 shadow-sm';
        card.dataset.sightingId = sighting.id;
        card.style.cursor = 'pointer';
        card.title = 'Click to show on map';

        // innerHTML matches the PHP card template exactly
        card.innerHTML =
            '<div class="card-body">' +
            '<h5 class="card-title d-flex justify-content-between align-items-center">' +
            sighting.pet_name +
            '<span class="' + textColor + '">' + statusLabel + '</span>' +
            '</h5>' +
            '<p class="card-text mb-2">' +
            '<strong>Reported by:</strong> ' + sighting.username + '<br>' +
            '<strong>Comment:</strong> ' + sighting.comment  + '<br>' +
            '<strong>Location:</strong> ' + sighting.lat + ', ' + sighting.lng + '<br>' +
            '<strong>Time:</strong> ' + sighting.timestamp +
            '</p>' +
            '<p class="text-muted small mb-0">' +
            'Sighting ID: ' + sighting.id +
            '&nbsp;·&nbsp;<span style="color:#0d6efd;">📍 Click card to show on map</span>' +
            '</p>' +
            '</div>';

        col.appendChild(card);
        return col;
    }

    // Re-bind click events on newly rendered cards so map focus still works
    _bindCardClicks() {
        this.cardGrid.querySelectorAll('[data-sighting-id]').forEach((card) => {
            card.addEventListener('click', () => {
                var id = parseInt(card.dataset.sightingId);
                if (window._petWatchMap) {
                    window._petWatchMap.focusMarker(id);
                }
            });
        });
    }

    // UI state helpers

    _showLoading() {
        if (this.countText) this.countText.textContent = 'Searching…';
    }

    _showError(message) {
        if (this.cardGrid) {
            this.cardGrid.innerHTML = '<p class="text-center text-danger">' + message + '</p>';
        }
    }

    // Restore pagination and the original count text when filters are cleared
    _exitLiveMode() {
        if (!this.isLiveMode) return;
        if (this.pagination) this.pagination.style.display = '';
        this.isLiveMode = false;
    }
}