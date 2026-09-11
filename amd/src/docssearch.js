// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Client-side search for the CodeRunner in-plugin documentation.
 *
 * The search is a bar docked at the top of the page that opens into a centered
 * modal "command palette" over a dimmed backdrop (click the bar or press
 * Ctrl+K). Choosing a result closes the palette and navigates to the section.
 *
 * The section index is fetched lazily (only on first use) from
 * docs_searchindex.php and cached; filtering then happens entirely in the
 * browser, which is ample for the small documentation corpus.
 *
 * @module     qtype_coderunner/docssearch
 * @copyright  2026 Michal Skraburski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
  'use strict';

  var indexPromise = null;

  /**
   * Fetch the section index once and cache the promise.
   * @param {String} url the searchindex endpoint.
   * @return {Promise<Array>} the index entries (empty array on failure).
   */
  function loadIndex(url) {
    if (!indexPromise) {
      indexPromise = fetch(url, {credentials: 'same-origin'})
        .then(function(response) {
          return response.ok ? response.json() : [];
        })
        .catch(function() {
          return [];
        });
    }
    return indexPromise;
  }

  /**
   * Escape a string for safe insertion into innerHTML.
   * @param {String} text
   * @return {String}
   */
  function escapeHtml(text) {
    var map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'};
    return text.replace(/[&<>"']/g, function(ch) {
      return map[ch];
    });
  }

  /**
   * Escape a string for use inside a regular expression.
   * @param {String} text
   * @return {String}
   */
  function escapeRegExp(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  /**
   * Rank the index against the query and return the best matches.
   * @param {Array} entries the section index.
   * @param {String} query the raw query string.
   * @return {Array} up to eight matching entries, best first.
   */
  function rank(entries, query) {
    var q = query.toLowerCase();
    var terms = q.split(/\s+/).filter(Boolean);
    var hits = [];
    entries.forEach(function(entry) {
      var heading = entry.heading.toLowerCase();
      var text = entry.text.toLowerCase();
      var matchesAll = terms.every(function(term) {
        return heading.indexOf(term) !== -1 || text.indexOf(term) !== -1;
      });
      if (!matchesAll) {
        return;
      }
      var score = 0;
      if (heading.indexOf(q) !== -1) {
        score += 100;
      } else if (text.indexOf(q) !== -1) {
        score += 30;
      }
      terms.forEach(function(term) {
        if (heading.indexOf(term) !== -1) {
          score += 10;
        }
        if (text.indexOf(term) !== -1) {
          score += 1;
        }
      });
      hits.push({entry: entry, score: score});
    });
    hits.sort(function(a, b) {
      return b.score - a.score;
    });
    return hits.slice(0, 8).map(function(hit) {
      return hit.entry;
    });
  }

  /**
   * Build a short, highlighted snippet around the first matching term.
   * @param {String} text the section text (already plain).
   * @param {Array} terms the lower-cased query terms.
   * @return {String} escaped HTML with matches wrapped in <mark>.
   */
  function snippet(text, terms) {
    var lower = text.toLowerCase();
    var pos = -1;
    terms.forEach(function(term) {
      var i = lower.indexOf(term);
      if (i !== -1 && (pos === -1 || i < pos)) {
        pos = i;
      }
    });
    var start = pos > 40 ? pos - 40 : 0;
    var slice = text.substr(start, 160);
    if (start > 0) {
      slice = '…' + slice;
    }
    if (start + 160 < text.length) {
      slice += '…';
    }
    var html = escapeHtml(slice);
    terms.forEach(function(term) {
      html = html.replace(new RegExp('(' + escapeRegExp(term) + ')', 'gi'), '<mark>$1</mark>');
    });
    return html;
  }

  /**
   * Wire up the documentation search palette.
   * @param {String} indexUrl the searchindex endpoint.
   * @param {String} pageBaseUrl the base docs.php URL for result links.
   */
  function init(indexUrl, pageBaseUrl) {
    var overlay = document.getElementById('coderunner-docs-search');
    var trigger = document.getElementById('coderunner-docs-search-trigger');
    if (!overlay || !trigger) {
      return;
    }
    var input = overlay.querySelector('.docs-search-input');
    var resultsEl = overlay.querySelector('.docs-search-results');
    var statusEl = overlay.querySelector('.docs-search-status');
    var current = [];
    var selected = -1;
    var debounce = null;

    /**
     * Build the link a result points at.
     * @param {Object} entry
     * @return {String}
     */
    function linkFor(entry) {
      return pageBaseUrl + '?page=' + encodeURIComponent(entry.page) + '#' + entry.anchor;
    }

    /**
     * Empty the results list and reset selection state.
     */
    function clearResults() {
      resultsEl.innerHTML = '';
      if (statusEl) {
        statusEl.textContent = '';
      }
      input.setAttribute('aria-expanded', 'false');
      current = [];
      selected = -1;
    }

    /**
     * @return {Boolean} whether the palette is currently open.
     */
    function isOpen() {
      return overlay.classList.contains('open');
    }

    /**
     * Open the palette (centered modal) and focus the input.
     */
    function open() {
      if (isOpen()) {
        input.focus();
        return;
      }
      input.value = '';
      clearResults();
      overlay.classList.add('open');
      overlay.setAttribute('aria-hidden', 'false');
      input.focus();
      loadIndex(indexUrl);
    }

    /**
     * Close the palette and return focus to the docked trigger.
     */
    function close() {
      if (!isOpen()) {
        return;
      }
      overlay.classList.remove('open');
      overlay.setAttribute('aria-hidden', 'true');
      clearResults();
      trigger.focus();
    }

    /**
     * Close the palette and navigate to the chosen section.
     * @param {Object} entry
     */
    function go(entry) {
      var href = linkFor(entry);
      close();
      window.location.href = href;
    }

    /**
     * Reflect the current keyboard selection onto the rendered options.
     */
    function markSelected() {
      var options = resultsEl.querySelectorAll('.docs-search-result');
      options.forEach(function(option, i) {
        if (i === selected) {
          option.classList.add('selected');
          option.setAttribute('aria-selected', 'true');
          option.scrollIntoView({block: 'nearest'});
        } else {
          option.classList.remove('selected');
          option.removeAttribute('aria-selected');
        }
      });
    }

    /**
     * Render the current results for the given query.
     * @param {String} query
     */
    function render(query) {
      var terms = query.toLowerCase().split(/\s+/).filter(Boolean);
      if (!current.length) {
        resultsEl.innerHTML = '<div class="docs-search-empty">No matches</div>';
        if (statusEl) {
          statusEl.textContent = 'No results';
        }
      } else {
        resultsEl.innerHTML = current.map(function(entry, i) {
          // Path source: the heading trail from the page's top heading down to
          // the subheading this match sits in (the emphasised last segment),
          // e.g. "Example Walkthroughs › Hello, World! › ... question type panel".
          var path = (entry.context || []).concat([entry.heading]);
          var last = path.length - 1;
          var pathHtml = path.map(function(seg, j) {
            var cls = j === last ? 'docs-search-path-match' : 'docs-search-path-seg';
            return '<span class="' + cls + '">' + escapeHtml(seg) + '</span>';
          }).join('<span class="docs-search-crumb-sep">›</span>');
          return '<a class="docs-search-result" role="option" id="docs-search-opt-' + i + '" href="'
            + escapeHtml(linkFor(entry)) + '">'
            + '<span class="docs-search-path">' + pathHtml + '</span>'
            + '<span class="docs-search-snippet">' + snippet(entry.text, terms) + '</span>'
            + '</a>';
        }).join('');
        if (statusEl) {
          statusEl.textContent = current.length + (current.length === 1 ? ' result' : ' results');
        }
      }
      input.setAttribute('aria-expanded', 'true');
    }

    /**
     * Run a search for the current input value.
     */
    function run() {
      var query = input.value.trim();
      if (query.length < 2) {
        clearResults();
        return;
      }
      loadIndex(indexUrl).then(function(entries) {
        current = rank(entries, query);
        selected = -1;
        render(query);
        return entries;
      });
    }

    trigger.addEventListener('click', open);

    input.addEventListener('input', function() {
      window.clearTimeout(debounce);
      debounce = window.setTimeout(run, 150);
    });

    input.addEventListener('keydown', function(e) {
      if (e.key === 'ArrowDown' && current.length) {
        e.preventDefault();
        selected = (selected + 1) % current.length;
        markSelected();
      } else if (e.key === 'ArrowUp' && current.length) {
        e.preventDefault();
        selected = (selected - 1 + current.length) % current.length;
        markSelected();
      } else if (e.key === 'Enter' && selected >= 0 && current[selected]) {
        e.preventDefault();
        go(current[selected]);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        close();
      }
    });

    // Choosing a result with the mouse closes the palette and navigates.
    resultsEl.addEventListener('click', function(e) {
      var link = e.target.closest('.docs-search-result');
      if (!link) {
        return;
      }
      e.preventDefault();
      var i = parseInt(link.id.replace('docs-search-opt-', ''), 10);
      if (current[i]) {
        go(current[i]);
      }
    });

    // Clicking the dimmed backdrop (outside the panel) closes the palette.
    overlay.addEventListener('mousedown', function(e) {
      if (e.target === overlay) {
        close();
      }
    });

    // Ctrl+K opens the palette from anywhere on the page. Ctrl only -- no Cmd.
    document.addEventListener('keydown', function(e) {
      if (e.ctrlKey && !e.metaKey && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        open();
      }
    });
  }

  return {init: init};
});
