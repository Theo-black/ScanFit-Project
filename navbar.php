<?php
// navbar.php

if (!function_exists('isLoggedIn')) {
    require_once 'functions.php';
}

$cartCount    = 0;
$customerInfo = null;
$mfaEnabled   = 0;
$navGradient  = 'linear-gradient(135deg,#667eea 0%,#764ba2 100%)';
$themeVarsCss = "
:root{
  --app-bg: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
  --app-surface:#ffffff;
  --app-surface-soft:#f8fafc;
  --app-text:#1f2937;
  --app-muted:#6b7280;
  --app-accent:#4f46e5;
  --app-accent-2:#7c3aed;
}
";

if (isLoggedIn()) {
    $customerId   = getCustomerId();
    $cartCount    = getCartItemCount($customerId);
    $customerInfo = getCustomerInfo($customerId);

    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT mfaenabled FROM customer WHERE customer_id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $customerId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $mfaEnabled = (int)$row['mfaenabled'];
        }
    }

    $themePreference = $customerInfo['theme_preference'] ?? 'default';
    $themeGradients = [
        'default' => 'linear-gradient(135deg,#667eea 0%,#764ba2 100%)',
        'ocean'   => 'linear-gradient(135deg,#0ea5e9 0%,#0369a1 100%)',
        'forest'  => 'linear-gradient(135deg,#16a34a 0%,#166534 100%)',
        'sunset'  => 'linear-gradient(135deg,#f97316 0%,#b91c1c 100%)',
        'slate'   => 'linear-gradient(135deg,#334155 0%,#0f172a 100%)',
    ];
    $themeVarMap = [
        'default' => "
            :root{
              --app-bg: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
              --app-surface:#ffffff;
              --app-surface-soft:#f8fafc;
              --app-text:#1f2937;
              --app-muted:#6b7280;
              --app-accent:#4f46e5;
              --app-accent-2:#7c3aed;
            }
        ",
        'ocean' => "
            :root{
              --app-bg: linear-gradient(135deg,#0ea5e9 0%,#0369a1 100%);
              --app-surface:#f0f9ff;
              --app-surface-soft:#e0f2fe;
              --app-text:#082f49;
              --app-muted:#0c4a6e;
              --app-accent:#0284c7;
              --app-accent-2:#0369a1;
            }
        ",
        'forest' => "
            :root{
              --app-bg: linear-gradient(135deg,#16a34a 0%,#166534 100%);
              --app-surface:#f0fdf4;
              --app-surface-soft:#dcfce7;
              --app-text:#14532d;
              --app-muted:#166534;
              --app-accent:#15803d;
              --app-accent-2:#166534;
            }
        ",
        'sunset' => "
            :root{
              --app-bg: linear-gradient(135deg,#f97316 0%,#b91c1c 100%);
              --app-surface:#fff7ed;
              --app-surface-soft:#ffedd5;
              --app-text:#7c2d12;
              --app-muted:#9a3412;
              --app-accent:#ea580c;
              --app-accent-2:#c2410c;
            }
        ",
        'slate' => "
            :root{
              --app-bg: linear-gradient(135deg,#334155 0%,#0f172a 100%);
              --app-surface:#111827;
              --app-surface-soft:#1f2937;
              --app-text:#e5e7eb;
              --app-muted:#cbd5e1;
              --app-accent:#38bdf8;
              --app-accent-2:#0ea5e9;
            }
        ",
    ];
    if (isset($themeGradients[$themePreference])) {
        $navGradient = $themeGradients[$themePreference];
    }
    if (isset($themeVarMap[$themePreference])) {
        $themeVarsCss = $themeVarMap[$themePreference];
    }
    if ($themePreference === 'custom' && !empty($customerInfo['theme_custom_json'])) {
        $decoded = json_decode((string)$customerInfo['theme_custom_json'], true);
        if (is_array($decoded)) {
            $keys = ['bg_start','bg_end','surface','surface_soft','text','muted','accent','accent_2'];
            $ok = true;
            foreach ($keys as $k) {
                if (!isset($decoded[$k]) || !is_string($decoded[$k]) || !preg_match('/^#[0-9a-fA-F]{6}$/', $decoded[$k])) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $bgStart = $decoded['bg_start'];
                $bgEnd = $decoded['bg_end'];
                $surface = $decoded['surface'];
                $surfaceSoft = $decoded['surface_soft'];
                $text = $decoded['text'];
                $muted = $decoded['muted'];
                $accent = $decoded['accent'];
                $accent2 = $decoded['accent_2'];
                $navGradient = "linear-gradient(135deg,{$bgStart} 0%,{$bgEnd} 100%)";
                $themeVarsCss = "
                    :root{
                      --app-bg: linear-gradient(135deg,{$bgStart} 0%,{$bgEnd} 100%);
                      --app-surface:{$surface};
                      --app-surface-soft:{$surfaceSoft};
                      --app-text:{$text};
                      --app-muted:{$muted};
                      --app-accent:{$accent};
                      --app-accent-2:{$accent2};
                    }
                ";
            }
        }
    }
}
?>
<style>
<?php echo $themeVarsCss; ?>
html,body{
    max-width:100%;
    overflow-x:hidden;
}
body{
    background:var(--app-bg) !important;
    color:var(--app-text) !important;
}
.calculator-card,.result-card,.info-section,.register-card,.login-container,.contact-form,.contact-info,.cart-items,.cart-summary,.checkout-form,.order-summary,.card,.section{
    background:var(--app-surface) !important;
    color:var(--app-text);
}
.subtitle,.hint,.field label,.section p,.result-item-label,.scan-meta,.measure-label{
    color:var(--app-muted) !important;
}
.btn,.submit-btn,.btn-primary,.cta-btn,.shop-btn,.checkout-btn,.place-order-btn,.view-btn{
    background:linear-gradient(135deg,var(--app-accent) 0%,var(--app-accent-2) 100%) !important;
    color:#fff !important;
}
a{color:var(--app-accent);}
.sf-nav{
    position:sticky;
    top:0;
    z-index:1000;
    box-shadow:0 4px 12px rgba(0,0,0,.15);
}
.sf-nav-shell{
    max-width:1400px;
    margin:0 auto;
    padding:1rem 1rem;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:1rem;
    position:relative;
}
.sf-brand{
    font-size:1.3rem;
    font-weight:800;
    color:#fff !important;
    text-decoration:none;
    letter-spacing:1px;
    white-space:nowrap;
}
.sf-menu-toggle{
    display:none;
    border:1px solid rgba(255,255,255,.45);
    background:rgba(255,255,255,.15);
    color:#fff;
    border-radius:10px;
    padding:.45rem .65rem;
    font-size:.92rem;
    font-weight:700;
}
.sf-nav-panel{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:1rem;
    flex:1;
    min-width:0;
}
.sf-nav-links{
    display:flex;
    align-items:center;
    gap:1rem;
    flex-wrap:wrap;
}
.sf-link{
    color:#fff !important;
    text-decoration:none;
    font-weight:600;
    opacity:1;
    transition:opacity .2s ease;
}
.sf-link:hover{
    opacity:.82;
}
.sf-search{
    margin:0;
}
.sf-search-input{
    width:min(42vw,260px);
    min-width:150px;
    padding:.55rem .9rem;
    border:none;
    border-radius:999px;
    outline:none;
}
.sf-nav-actions{
    display:flex;
    align-items:center;
    gap:.9rem;
    flex-wrap:wrap;
}
.sf-pill{
    display:flex;
    align-items:center;
    gap:.65rem;
    background:rgba(255,255,255,.15);
    padding:.45rem .85rem;
    border-radius:999px;
    border:2px solid rgba(255,255,255,.3);
    transition:background .2s ease,border-color .2s ease,transform .2s ease;
}
.sf-pill:hover{
    background:rgba(255,255,255,.3);
    border-color:#fff;
    transform:translateY(-1px);
}
.sf-pill-name{
    color:#fff;
    font-weight:700;
    font-size:.9rem;
}
.sf-profile-img{
    width:30px;
    height:30px;
    border-radius:50%;
    object-fit:cover;
    border:2px solid rgba(255,255,255,.7);
}
.sf-signup{
    color:#fff !important;
    text-decoration:none;
    font-weight:600;
    background:rgba(255,255,255,.2);
    padding:.5rem 1rem;
    border-radius:999px;
    transition:background .2s ease;
}
.sf-signup:hover{
    background:rgba(255,255,255,.32);
}
.sf-cart{
    position:relative;
}
.sf-cart-count{
    position:absolute;
    top:-8px;
    right:-12px;
    background:#ff4444;
    color:#fff;
    border-radius:999px;
    padding:2px 6px;
    font-size:.75rem;
    font-weight:700;
}
@media (max-width:980px){
    .sf-menu-toggle{
        display:inline-flex;
        align-items:center;
        justify-content:center;
    }
    .sf-nav-shell{
        padding:.9rem 1rem;
    }
    .sf-nav-panel{
        display:none;
        position:absolute;
        left:0;
        right:0;
        top:100%;
        padding:.8rem 1rem 1rem;
        background:rgba(0,0,0,.27);
        backdrop-filter:blur(4px);
        border-top:1px solid rgba(255,255,255,.25);
        flex-direction:column;
        align-items:stretch;
        gap:.8rem;
    }
    .sf-nav-panel.is-open{
        display:flex;
    }
    .sf-nav-links,.sf-nav-actions{
        flex-direction:column;
        align-items:stretch;
        gap:.45rem;
    }
    .sf-link,.sf-signup{
        display:block;
        padding:.6rem .75rem;
        border-radius:10px;
        background:rgba(255,255,255,.12);
    }
    .sf-search{
        width:100%;
    }
    .sf-search-input{
        width:100%;
        min-width:0;
    }
    .sf-pill{
        justify-content:flex-start;
        width:100%;
    }
}
@media (prefers-reduced-motion: reduce){
    .sf-link,.sf-pill,.sf-signup{
        transition:none !important;
    }
}
</style>
<nav class="sf-nav" style="background:<?php echo htmlspecialchars($navGradient); ?>">
    <div class="sf-nav-shell">
        <a href="index.php" class="sf-brand">
            SCANFIT
        </a>
        <button type="button" class="sf-menu-toggle" aria-expanded="false" aria-controls="sf-nav-panel">Menu</button>

        <div class="sf-nav-panel" id="sf-nav-panel">
            <div class="sf-nav-links">
                <a href="index.php" class="sf-link">Home</a>
                <a href="men.php" class="sf-link">Men</a>
                <a href="womens.php" class="sf-link">Women</a>
                <a href="accessories.php" class="sf-link">Accessories</a>
                <a href="bmi_calculator.php" class="sf-link">Size Guide</a>
                <a href="about.php" class="sf-link">About</a>
                <a href="contact.php" class="sf-link">Contact</a>
            </div>

            <form method="GET" action="search.php" class="sf-search">
                <input type="text" name="q" placeholder="Search products..."
                       class="sf-search-input"
                       required>
            </form>

            <div class="sf-nav-actions">
                <?php if (isLoggedIn()): ?>
                    <a href="cart.php" class="sf-link sf-cart">
                        Cart
                        <?php if ($cartCount > 0): ?>
                            <span class="sf-cart-count"><?php echo $cartCount; ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="orders.php" class="sf-link">Orders</a>
                    <a href="wishlist.php" class="sf-link">Wishlist</a>

                    <a href="settings.php#saved-sizes" style="text-decoration:none;">
                        <div class="sf-pill">
                            <?php if (!empty($customerInfo['profile_image'])): ?>
                                <img
                                    src="<?php echo htmlspecialchars($customerInfo['profile_image']); ?>"
                                    alt="Profile"
                                    class="sf-profile-img"
                                >
                            <?php else: ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                     stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            <?php endif; ?>
                            <span class="sf-pill-name">
                                <?php
                                    if ($customerInfo) {
                                        echo htmlspecialchars($customerInfo['first_name'] . ' ' . $customerInfo['last_name']);
                                    } else {
                                        echo 'Profile';
                                    }
                                ?>
                            </span>
                        </div>
                    </a>

                    <a href="logout.php" class="sf-link">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="sf-link">Login</a>
                    <a href="register.php" class="sf-signup">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<script>
(function () {
    var toggle = document.querySelector('.sf-menu-toggle');
    var panel = document.getElementById('sf-nav-panel');
    if (!toggle || !panel) return;

    toggle.addEventListener('click', function () {
        var open = panel.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    panel.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (target.closest('a')) {
            panel.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 980) {
            panel.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    var imgs = document.querySelectorAll('img:not(.sf-profile-img):not([loading])');
    imgs.forEach(function (img, index) {
        if (index > 0) {
            img.setAttribute('loading', 'lazy');
            img.setAttribute('decoding', 'async');
        }
    });
})();
</script>
