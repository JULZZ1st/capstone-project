<script>
document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('[data-fund-request-location-form]');

    const refreshSelect = (select) => select.dispatchEvent(new Event('custom-select-refresh', { bubbles: true }));
    const selectedCode = (select) => select.selectedOptions[0]?.dataset.code || '';
    const populate = (select, items, placeholder, selectedValue = '') => {
        select.innerHTML = '';
        const first = new Option(placeholder, '');
        select.append(first);
        items.forEach((item) => select.append(new Option(item.name, item.name)));
        items.forEach((item, index) => {
            const option = select.options[index + 1];
            option.dataset.code = item.code;
            if (selectedValue && item.name.toLowerCase() === selectedValue.toLowerCase()) option.selected = true;
        });
        select.disabled = items.length === 0;
        refreshSelect(select);
    };
    const load = (url) => fetch(url, { headers: { Accept: 'application/json' } }).then((response) => response.ok ? response.json() : []).catch(() => []);

    forms.forEach((form) => {
        const province = form.querySelector('[data-location-province]');
        const city = form.querySelector('[data-location-city]');
        const barangay = form.querySelector('[data-location-barangay]');
        if (!province || !city || !barangay) return;

        const loadBarangays = (cityCode, selected = '') => {
            populate(barangay, [], 'Select barangay');
            if (!cityCode) return Promise.resolve();
            barangay.disabled = true;
            return load(`/locations/barangays?city_code=${encodeURIComponent(cityCode)}`).then((items) => populate(barangay, items, 'Select barangay', selected));
        };
        const loadCities = (provinceCode, selectedCity = '', selectedBarangay = '') => {
            populate(city, [], 'Select city / municipality');
            populate(barangay, [], 'Select barangay');
            if (!provinceCode) return Promise.resolve();
            city.disabled = true;
            return load(`/locations/cities?province_code=${encodeURIComponent(provinceCode)}`).then((items) => {
                populate(city, items, 'Select city / municipality', selectedCity);
                return loadBarangays(selectedCode(city), selectedBarangay);
            });
        };

        province.addEventListener('change', () => loadCities(selectedCode(province)));
        city.addEventListener('change', () => loadBarangays(selectedCode(city)));

        const selectedCity = city.dataset.selectedValue || '';
        const selectedBarangay = barangay.dataset.selectedValue || '';
        loadCities(selectedCode(province), selectedCity, selectedBarangay);
    });
});
</script>
