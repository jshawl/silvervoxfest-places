export const createElement = ({ type, emoji }) => {
  if (!type || !emoji) {
    return undefined;
  }
  const element = document.createElement("div");
  element.classList.add(`svf-marker-${type}`);
  element.innerHTML = emoji;
  return element;
};

let markers = [];
export const getMarkers = () => markers;

export const createMarkers = ({ locations }) => {
  locations.forEach((location) => {
    markers.push({ marker: createMarker(location), location });
  });
  return markers;
};

export const ICON_MAP = {
  bar: { emoji: "🍻", label: "Bars" },
  coffee: { emoji: "☕️", label: "Coffee Shops" },
  hotel: { emoji: "🏨", label: "Hotels" },
  parking: { emoji: "🅿️", label: "Parking" },
  restaurant: { emoji: "🍽️", label: "Restaurants" },
  shopping: { emoji: "🛍️", label: "Shopping" },
  venue: { emoji: "🎭", label: "Venues" },
};

export const createMarker = (location) => {
  const options = {
    element: createElement({
      type: location.type,
      emoji: ICON_MAP[location.type.toLowerCase()]?.emoji,
    }),
    draggable: /drag/.test(window.location.hash),
  };

  const marker = new mapboxgl.Marker(options)
    .setLngLat([location.lng, location.lat])
    .setPopup(
      // TODO h3 line height
      new mapboxgl.Popup({ focusAfterOpen: false, offset: 25 }).setHTML(
        `<h3>${location.title.rendered}</h3>
               ${location.content.rendered ?? ""}
               <p>
                <a href='https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(location.address)}' target='_blank'>${location.address}</a>
               </p>
               <p><a href='${location.url}' target='_blank'>${location.url}</a></p>`,
      ),
    );
  marker.getElement().addEventListener("click", (e) => {
    e.stopPropagation();
    closePopups();
    marker.togglePopup();
  });
  marker.on('dragend', () => {
    const lngLat = marker.getLngLat();
    const confirmed = confirm("Are you sure you want to update this place?")
    if (!confirmed) {
      return
    }
    fetch(window.placesMarkerData.ajax_url, {
      method: "POST",
      body: new URLSearchParams({
        action: "update_place",
        nonce: window.placesMarkerData.nonce,
        post_id: location.id,
        lat: lngLat.lat,
        lng: lngLat.lng
      })
    })
  });
  return marker;
};

export const closePopups = () => {
  markers.forEach(({ marker }) => {
    if (marker.getPopup()?.isOpen()) {
      marker.togglePopup();
    }
  });
};
