/**
 * @file
 * County map and deal location maps for goingsolo.ie.
 */
((Drupal, drupalSettings, once, L) => {
  const scale = ['#dcebe3', '#b3d4c0', '#7fb69b', '#3f8c6e', '#0b4f4a'];

  function colourFor(count, max) {
    if (!count) {
      return '#efe9df';
    }
    const step = Math.min(scale.length - 1, Math.ceil((count / Math.max(max, 1)) * (scale.length - 1)));
    return scale[Math.max(1, step)];
  }

  function countyMap(el, settings) {
    const counties = settings.counties || {};
    const max = Math.max(0, ...Object.values(counties).map((c) => c.count));
    const map = L.map(el, {
      zoomControl: false,
      scrollWheelZoom: false,
      dragging: !L.Browser.mobile,
      attributionControl: true,
      zoomSnap: 0.25,
    });
    map.attributionControl.setPrefix(false);
    map.attributionControl.addAttribution('Boundaries: Tailte Éireann (CC BY 4.0), OSNI');

    fetch(settings.geojson)
      .then((response) => response.json())
      .then((data) => {
        const layer = L.geoJSON(data, {
          style: (feature) => {
            const c = counties[feature.properties.name] || { count: 0 };
            return {
              color: '#ffffff',
              weight: 1.2,
              fillColor: colourFor(c.count, max),
              fillOpacity: 1,
            };
          },
          onEachFeature: (feature, featureLayer) => {
            const c = counties[feature.properties.name];
            if (!c) {
              return;
            }
            const label = c.count === 1
              ? Drupal.t('@county: 1 solo deal', { '@county': c.label })
              : Drupal.t('@county: @count solo deals', { '@county': c.label, '@count': c.count });
            featureLayer.bindTooltip(label, { sticky: true, direction: 'top' });
            featureLayer.on({
              mouseover: () => featureLayer.setStyle({ weight: 2.5, color: '#c23b6e' }),
              mouseout: () => featureLayer.setStyle({ weight: 1.2, color: '#ffffff' }),
              click: () => { window.location.href = c.url; },
            });
          },
        }).addTo(map);
        map.fitBounds(layer.getBounds(), { padding: [8, 8] });
      });

    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = () => {
      const div = L.DomUtil.create('div', 'gs-map__legend');
      div.innerHTML = `<span class="gs-map__legend-swatch" style="background:${scale[0]}"></span>${Drupal.t('Fewer')}<span class="gs-map__legend-swatch" style="background:${scale[4]}"></span>${Drupal.t('More solo deals')}`;
      return div;
    };
    legend.addTo(map);
  }

  function dealsMap(el, points) {
    const map = L.map(el, { scrollWheelZoom: false });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);
    const group = L.featureGroup();
    points.forEach((p) => {
      const marker = L.circleMarker([p.lat, p.lng], {
        radius: p.featured ? 10 : 8,
        color: '#ffffff',
        weight: 2,
        fillColor: p.featured ? '#c23b6e' : '#0b4f4a',
        fillOpacity: 0.95,
      });
      const link = document.createElement('a');
      link.href = p.url;
      link.textContent = p.title;
      const box = document.createElement('div');
      box.className = 'gs-map__popup';
      box.appendChild(link);
      if (p.price) {
        const price = document.createElement('div');
        price.textContent = `${p.price} ${p.basis}`.trim();
        box.appendChild(price);
      }
      marker.bindPopup(box);
      group.addLayer(marker);
    });
    group.addTo(map);
    if (points.length === 1) {
      map.setView([points[0].lat, points[0].lng], 11);
    }
    else {
      map.fitBounds(group.getBounds(), { padding: [30, 30], maxZoom: 11 });
    }
  }

  Drupal.behaviors.goingsoloMaps = {
    attach(context) {
      const settings = drupalSettings.goingsoloMaps || {};
      once('gs-map', '[data-gs-map="counties"]', context).forEach((el) => countyMap(el, settings));
      once('gs-map', '[data-gs-map="deals"]', context).forEach((el) => {
        const points = (settings.points || {})[el.dataset.gsMapId] || [];
        if (points.length) {
          dealsMap(el, points);
        }
      });
    },
  };
})(Drupal, drupalSettings, once, window.L);
