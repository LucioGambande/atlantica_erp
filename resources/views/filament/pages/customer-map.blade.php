<x-filament-panels::page>
    @php
        $customers = $this->getCustomers();
        $apiKey = $this->getGoogleMapsApiKey();
    @endphp

    @if (blank($apiKey))
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Falta configurar <code>GOOGLE_MAPS_API_KEY</code> en el <code>.env</code> para poder mostrar el mapa.
            </p>
        </div>
    @elseif (empty($customers))
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Ningún cliente tiene latitud/longitud cargada todavía. Completá esos datos desde la ficha del cliente.
            </p>
        </div>
    @else
        <div
            x-data="customerMap()"
            x-init="init()"
            wire:ignore
        >
            <div id="customer-map-canvas" style="width: 100%; height: 70vh; border-radius: 0.75rem;"></div>
        </div>

        <div class="mt-3 flex items-center gap-4 text-sm text-gray-600 dark:text-gray-300">
            <span class="flex items-center gap-1.5">
                <span class="inline-block h-3 w-3 rounded-full" style="background-color:#34a853;"></span>
                Con facturación
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block h-3 w-3 rounded-full" style="background-color:#ea4335;"></span>
                Sin facturación
            </span>
        </div>

        @php
            $mapCallback = 'initCustomerMap';
        @endphp

        <script>
            window.__customerMapData = @json($customers);

            function customerMap() {
                return {
                    init() {
                        window.{{ $mapCallback }} = () => {
                            const customers = window.__customerMapData || [];
                            const map = new google.maps.Map(document.getElementById('customer-map-canvas'), {
                                zoom: 6,
                                center: { lat: customers[0].lat, lng: customers[0].lng },
                            });

                            const bounds = new google.maps.LatLngBounds();
                            const infoWindow = new google.maps.InfoWindow();

                            customers.forEach((customer) => {
                                const position = { lat: customer.lat, lng: customer.lng };
                                const marker = new google.maps.Marker({
                                    position,
                                    map,
                                    title: customer.name,
                                    icon: customer.hasBilled
                                        ? 'https://maps.google.com/mapfiles/ms/icons/green-dot.png'
                                        : 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
                                });

                                marker.addListener('click', () => {
                                    const addressLine = [customer.address, customer.city].filter(Boolean).join(', ');
                                    infoWindow.setContent(
                                        `<strong>${customer.name}</strong>${addressLine ? `<br>${addressLine}` : ''}`
                                    );
                                    infoWindow.open(map, marker);
                                });

                                bounds.extend(position);
                            });

                            if (customers.length > 1) {
                                map.fitBounds(bounds);
                            }
                        };

                        const script = document.createElement('script');
                        script.src = `https://maps.googleapis.com/maps/api/js?key={{ $apiKey }}&callback={{ $mapCallback }}`;
                        script.async = true;
                        document.head.appendChild(script);
                    },
                };
            }
        </script>
    @endif
</x-filament-panels::page>
