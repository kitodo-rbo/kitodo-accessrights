/**
 * rbo-auth.js -- attaches "Authorization: Bearer <JWT>" to requests that go to the image server.
 *
 * Kitodo.Presentation's page viewer is built on OpenLayers and loads images in three ways.
 * Each needs its own hook, because a plain <img> tag can never send a header:
 *
 *   1. XMLHttpRequest  (static images, IIIF info.json, Zoomify ImageProperties.xml)
 *        -> patch XMLHttpRequest.send(): add the header for allow-listed origins.
 *   2. Tiles           (ol.source.IIIF / Zoomify / IIP)  -> loaded via <img> by default
 *        -> replace the source's tileLoadFunction: fetch() with header -> Blob -> <img>.
 *   3. Everything else (other hosts, thumbnails, fulltext) -> untouched.
 *
 * LOAD ORDER MATTERS: this file must run AFTER Kitodo's Utility.js (needs `dlfUtils`)
 * and BEFORE the viewer starts (it starts on DOM-ready).
 *
 * Expects (both written by the server, see README):
 *   sessionStorage['dlf.rbo.token']  the JWT for the current document
 *   window.DlfPdp.origins            array of origins that may receive the token
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'dlf.rbo.token';
  var TILE_STATE_ERROR = 3; // ol/TileState: IDLE 0, LOADING 1, LOADED 2, ERROR 3, EMPTY 4

  function getToken() {
    try { return window.sessionStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }

  // SECURITY: the token must only ever go to the image server. METS files can reference
  // images on other hosts; sending the bearer token there would hand it to a third party.
  function isProtectedUrl(url) {
    var origins = (window.DlfPdp && window.DlfPdp.origins) || [];
    try { return origins.indexOf(new URL(String(url), window.location.href).origin) !== -1; }
    catch (e) { return false; }
  }

  function authHeaderFor(url) {
    var token = getToken();
    return token && isProtectedUrl(url) ? 'Bearer ' + token : null;
  }

  // ---- 1. XMLHttpRequest ---------------------------------------------------------------
  var xhrOpen = XMLHttpRequest.prototype.open;
  var xhrSend = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function (method, url) {
    this._rboUrl = url;
    return xhrOpen.apply(this, arguments);
  };
  XMLHttpRequest.prototype.send = function () {
    var header = authHeaderFor(this._rboUrl);
    if (header) { this.setRequestHeader('Authorization', header); }
    return xhrSend.apply(this, arguments);
  };

  // ---- 2. Tiles ------------------------------------------------------------------------
  function makeTileLoader(fallback) {
    return function (tile, src) {
      var header = authHeaderFor(src);
      if (!header) { return fallback(tile, src); }   // foreign host: default behaviour, NO token

      var img = tile.getImage();
      fetch(src, { headers: { Authorization: header } })
        .then(function (res) {
          if (!res.ok) { throw new Error('HTTP ' + res.status); }
          return res.blob();
        })
        .then(function (blob) {
          var objectUrl = URL.createObjectURL(blob);
          img.addEventListener('load', function () { URL.revokeObjectURL(objectUrl); }, { once: true });
          img.src = objectUrl;
        })
        .catch(function () { tile.setState(TILE_STATE_ERROR); });
    };
  }

  function install() {
    if (typeof dlfUtils === 'undefined' || typeof dlfUtils.createOlLayers !== 'function') {
      console.warn('[rbo-auth] dlfUtils not found: this script loads BEFORE Kitodo\'s Utility.js. Tiles will be unauthenticated.');
      return false;
    }
    if (dlfUtils.createOlLayers.rboWrapped) { return true; }

    var original = dlfUtils.createOlLayers;
    dlfUtils.createOlLayers = function () {
      var layers = original.apply(this, arguments);
      layers.forEach(function (layer) {
        var source = layer.getSource && layer.getSource();
        if (source && typeof source.setTileLoadFunction === 'function') {
          source.setTileLoadFunction(makeTileLoader(source.getTileLoadFunction()));
        }
      });
      return layers;
    };
    dlfUtils.createOlLayers.rboWrapped = true;
    return true;
  }

  install();
  window.DlfPdpAuth = { installed: install, _authHeaderFor: authHeaderFor }; // handle for console debugging
})();
