@props(['name' => 'icon', 'value' => '', 'placeholder' => 'Select Icon', 'label' => 'Icon'])

@php
    $uniqueId = 'icon_' . uniqid() . '_' . rand(1000, 9999);
@endphp

<div class="form-group">
    <label class="form-label">{{ __($label) }}</label>
    <div class="input-group">
        <span class="input-group-text">
            <i id="preview-{{ $uniqueId }}" class="{{ $value ?: 'fa fa-user' }}"></i>
        </span>
        <input type="text" id="input-{{ $uniqueId }}" name="{{ $name }}" class="form-control"
               placeholder="{{ __($placeholder) }}" value="{{ $value }}" readonly>
        <button type="button" class="btn btn-outline-secondary" onclick="openIconModal('{{ $uniqueId }}')">Select</button>
    </div>
</div>

<div class="modal fade" id="modal-{{ $uniqueId }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Icon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="search-{{ $uniqueId }}" class="form-control mb-3" placeholder="Search icons...">
                <div id="icons-{{ $uniqueId }}" class="d-flex flex-wrap gap-2" style="max-height:300px;overflow-y:auto"></div>
            </div>
        </div>
    </div>
</div>

@once
<script>
window.iconPickerData = {
    icons: ['fa fa-home','fa fa-user','fa fa-search','fa fa-heart','fa fa-star','fa fa-envelope','fa fa-phone','fa fa-calendar','fa fa-clock','fa fa-map-marker','fa fa-edit','fa fa-trash','fa fa-plus','fa fa-minus','fa fa-check','fa fa-times','fa fa-arrow-left','fa fa-arrow-right','fa fa-arrow-up','fa fa-arrow-down','fa fa-cog','fa fa-bars','fa fa-download','fa fa-upload','fa fa-print','fa fa-share','fa fa-bookmark','fa fa-tag','fa fa-comment','fa fa-bell','fa fa-eye','fa fa-eye-slash','fa fa-lock','fa fa-unlock','fa fa-key','fab fa-shield','fa fa-globe','fa fa-wifi','fa fa-play','fa fa-pause','fa fa-stop','fa fa-camera','fa fa-image','fa fa-video','fa fa-music','fa fa-file','fa fa-folder','fa fa-save','fa fa-copy','fa fa-shopping-cart','fa fa-credit-card','fa fa-gift','fa fa-trophy','fa fa-thumbs-up','fa fa-thumbs-down','fa fa-smile','fab fa-facebook','fab fa-twitter','fab fa-instagram','fab fa-linkedin','fab fa-youtube','fab fa-github','fab fa-google','fa fa-apple','fa fa-microsoft','fa fa-amazon','fa fa-users','fa fa-building','fa fa-car','fa fa-plane','fa fa-train','fa fa-ship','fa fa-bicycle','fa fa-motorcycle','fa fa-bus','fa fa-taxi','fa fa-truck','fa fa-ambulance','fa fa-fire','fa fa-rocket','fa fa-moon','fa fa-sun','fa fa-cloud','fa fa-umbrella','fa fa-tree','fa fa-leaf','fa fa-bug','fa fa-cat','fa fa-dog','fa fa-fish','fa fa-microphone','fa fa-headphones','fa fa-tv','fa fa-laptop','fa fa-tablet','fa fa-mobile','fa fa-keyboard','fa fa-mouse','fa fa-gamepad','fa fa-coffee','fa fa-beer','fa fa-glass','fa fa-cutlery','fa fa-birthday-cake','fa fa-pizza-slice','fa fa-hamburger','fa fa-ice-cream','fa fa-cookie-bite','fa fa-apple-alt','fa fa-lemon','fa fa-pepper-hot','fa fa-carrot','fa fa-seedling','fa fa-tools','fa fa-hammer','fa fa-wrench','fa fa-screwdriver','fa fa-paint-brush','fa fa-palette','fa fa-pencil-alt','fa fa-pen','fa fa-marker','fa fa-highlighter','fa fa-eraser','fa fa-scissors','fa fa-paperclip','fa fa-thumbtack','fa fa-ruler','fa fa-calculator','fa fa-clipboard','fa fa-sticky-note','fa fa-book','fa fa-newspaper','fa fa-magazine','fa fa-graduation-cap','fa fa-school','fa fa-university','fa fa-hospital','fa fa-clinic-medical','fa fa-pills','fa fa-syringe','fa fa-thermometer','fa fa-heartbeat','fa fa-band-aid','fa fa-wheelchair','fa fa-baby','fa fa-child','fa fa-male','fa fa-female','fa fa-restroom','fa fa-walking','fa fa-running','fa fa-biking','fa fa-swimming-pool','fa fa-dumbbell','fa fa-weight','fa fa-baseball-ball','fa fa-basketball-ball','fa fa-football-ball','fa fa-volleyball-ball','fa fa-table-tennis','fa fa-bowling-ball','fa fa-golf-ball','fa fa-hockey-puck','fa fa-dice','fa fa-chess','fa fa-puzzle-piece','fa fa-headset','fa fa-ticket-alt','fa fa-film','fa fa-camera-retro','fa fa-photo-video','fa fa-compact-disc','fa fa-record-vinyl','fa fa-guitar','fa fa-drum','fa fa-microphone-alt','fa fa-volume-up','fa fa-volume-down','fa fa-volume-mute','fa fa-broadcast-tower','fa fa-satellite','fa fa-satellite-dish','fa fa-phone-alt','fa fa-fax','fa fa-address-book','fa fa-id-card','fa fa-passport','fa fa-briefcase','fa fa-suitcase','fa fa-luggage-cart','fa fa-shopping-bag','fa fa-shopping-basket','fa fa-store','fa fa-cash-register','fa fa-receipt','fa fa-coins','fa fa-dollar-sign','fa fa-euro-sign','fa fa-pound-sign','fa fa-yen-sign','fa fa-ruble-sign','fa fa-rupee-sign','fa fa-won-sign','fa fa-bitcoin','fa fa-ethereum','fa fa-chart-line','fa fa-chart-bar','fa fa-chart-pie','fa fa-chart-area','fa fa-analytics','fa fa-tachometer-alt','fa fa-clipboard-list','fa fa-tasks','fa fa-project-diagram','fa fa-sitemap','fa fa-code','fa fa-code-branch','fa fa-terminal','fa fa-server','fa fa-database','fa fa-hdd','fa fa-memory','fa fa-microchip','fa fa-plug','fa fa-battery-full','fa fa-battery-half','fa fa-battery-empty','fa fa-charging-station','fa fa-solar-panel','fa fa-wind','fa fa-water','fa fa-oil-can','fa fa-gas-pump','fa fa-recycle','fa fa-trash-alt','fa fa-dumpster','fa fa-broom','fa fa-spray-can','fa fa-fire-extinguisher','fa fa-toolbox','fa fa-hard-hat','fa fa-vest','fa fa-vest-patches','fa fa-id-badge','fa fa-clipboard-check','fa fa-stamp','fa fa-envelope-open','fa fa-envelope-square','fa fa-inbox','fa fa-outbox','fa fa-paper-plane','fa fa-mail-bulk','fa fa-at','fa fa-hashtag','fa fa-percent','fa fa-asterisk','fa fa-exclamation','fa fa-question','fa fa-info','fa fa-ban','fa fa-exclamation-triangle','fa fa-check-circle','fa fa-times-circle','fa fa-dot-circle','fa fa-circle','fa fa-square','fa fa-square-full','fa fa-heart-broken','fa fa-star-half','fa fa-star-half-alt','fa fa-user-md','fa fa-brain','fa fa-spa','fa fa-award','fa fa-cut','fa fa-hand-sparkles','fa fa-smile-beam','fa fa-magic','fa fa-hand-holding-water','fa fa-calendar-check','fa fa-tags','fa fa-wallet','fas fa-qrcode','fa-piggy-bank','fa fa-file-invoice-dollar', 'fa fa-user-shield', 'fa fa-user-secret', 'fa fa-user-tie', 'fa fa-user-ninja', 'fa fa-binoculars', 'fa fa-compass','fa fa-flask', 'fa fa-globe-europe', 'fa fa-certificate', 'fa fa-flag-checkered', 'fa fa-medal', 'fa fa-handshake', 'fa fa-mountain', 'fa fa-money-bill-wave', 'fa fa-warehouse','fa fa-landmark', 'fab fa-pinterest', 'fab fa-behance', 'fab fa-dribbble', 'fab fa-snapchat', 'fab fa-whatsapp', 'fab fa-telegram', 'fab fa-discord', 'fab fa-reddit', 'fab fa-skype', 'fab fa-tumblr', 'fab fa-vk', 'fab fa-weibo', 'fab fa-medium', 'fab fa-flickr', 'fab fa-vimeo', 'fab fa-slack', 'fa fa-chess-knight','fas fa-shield-alt','fas fa-sliders-h','fas fas fa-hand-holding-usd','fa fa-lightbulb','fas fa-robot','fab fa-html5', 'fab fa-css3-alt', 'fab fa-js', 'fab fa-php', 'fab fa-python','fab fa-java', 'fas fa-code', 'fab fa-swift', 'fas fa-terminal', 'fas fa-gem','fas fa-cog', 'fas fa-database', 'fab fa-react', 'fab fa-angular', 'fab fa-vuejs','fab fa-laravel', 'fab fa-node-js', 'fab fa-bootstrap', 'fas fa-leaf', 'fas fa-fire','fas fa-laptop-code', 'fas fa-server', 'fas fa-plug', 'fas fa-tools', 'fas fa-cogs','fas fa-pencil-ruler', 'fas fa-vial', 'fas fa-cloud', 'fab fa-git', 'fab fa-github','fab fa-gitlab', 'fab fa-bitbucket', 'fab fa-docker','fas fa-brain','fab fa-aws', 'fab fa-bitcoin']
};

window.openIconModal = function(id) {
    const modal = new bootstrap.Modal(document.getElementById(`modal-${id}`));
    const container = document.getElementById(`icons-${id}`);
    const search = document.getElementById(`search-${id}`);

    window.renderIcons(container, window.iconPickerData.icons, id);

    search.oninput = () => {
        const filtered = window.iconPickerData.icons.filter(icon => icon.includes(search.value.toLowerCase()));
        window.renderIcons(container, filtered, id);
    };

    modal.show();
};

window.renderIcons = function(container, iconList, id) {
    container.innerHTML = iconList.map(icon =>
        `<div class="p-2 border rounded text-center" style="cursor:pointer;min-width:60px" onclick="selectIcon('${icon}', '${id}')">
            <i class="${icon} d-block mb-1"></i>
            <small>${icon.split(' ').pop().replace('fa-', '')}</small>
        </div>`
    ).join('');
};

window.selectIcon = function(icon, id) {
    document.getElementById(`input-${id}`).value = icon;
    document.getElementById(`preview-${id}`).className = icon;
    bootstrap.Modal.getInstance(document.getElementById(`modal-${id}`)).hide();
};
</script>
@endonce
