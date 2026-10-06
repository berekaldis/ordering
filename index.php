<?php
require_once 'config.php';

// Auto-redirect root domain access (e.g. ordering.kaldisbunnaet.com/) to /eca
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($uri, '/eca') === false && (empty($uri) || $uri === '/' || $uri === '/index.php')) {
    $chatId = $_GET['chat_id'] ?? '';
    $redirectUrl = '/eca/';
    if (!empty($chatId)) {
        $redirectUrl .= '?chat_id=' . urlencode($chatId);
    }
    header("Location: " . $redirectUrl, true, 302);
    exit;
}

// Check if we're in Telegram WebView
$isTelegramWebView = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'TelegramBot') !== false;
$chatId = $_GET['chat_id'] ?? '';

if ($isTelegramWebView || $chatId) {
    // Redirect to mini app
    header('Location: miniapp/index.php?chat_id=' . urlencode($chatId));
    exit;
}

$logoUrl = getLogoUrl() ?: 'uploads/logo/kaldis-logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kaldis Coffee - ECA Branch | Office Coffee & Food Delivery</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logoUrl); ?>">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($logoUrl); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Ethiopic:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Plus Jakarta Sans', 'Noto Sans Ethiopic', sans-serif; }
        body {
            background: linear-gradient(135deg, #FAF6F0 0%, #F5EFEB 50%, #EDE4DC 100%);
            min-height: 100vh;
        }
        .hero-pattern {
            background-color: #FAF6F0;
            background-image: radial-gradient(rgba(180, 83, 9, 0.08) 1.2px, transparent 0);
            background-size: 24px 24px;
        }
        .coffee-glow {
            box-shadow: 0 10px 30px -5px rgba(217, 119, 6, 0.2);
        }
    </style>
</head>
<body class="hero-pattern text-stone-800 flex flex-col min-h-screen">
    <!-- Top Header -->
    <header class="w-full border-b border-amber-200/60 bg-white/80 backdrop-blur-md sticky top-0 z-50 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Kaldis Coffee" class="h-10 w-auto object-contain rounded-md">
                <div>
                    <span class="font-extrabold text-lg tracking-tight text-stone-900 block leading-tight">Kaldis Coffee</span>
                    <span class="text-xs text-amber-700 font-semibold tracking-wide">ECA Branch • Office Delivery</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Hero -->
    <main class="flex-1 flex items-center justify-center p-4 py-10">
        <div class="max-w-xl w-full bg-white border border-amber-100 rounded-3xl shadow-xl shadow-amber-950/5 overflow-hidden">
            <!-- Banner Header -->
            <div class="relative bg-gradient-to-br from-amber-100/80 via-amber-50 to-orange-50/50 p-8 text-center border-b border-amber-100">
                <div class="w-20 h-20 bg-white border border-amber-200 rounded-2xl flex items-center justify-center mx-auto mb-4 coffee-glow shadow-md">
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Kaldis" class="h-12 w-auto object-contain">
                </div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-100 border border-amber-200 text-amber-900 text-xs font-bold mb-3 shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    UNECA Compound Desk Delivery
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-stone-900 tracking-tight">Order Straight to Your Office</h1>
                <p class="text-stone-600 text-sm mt-2 max-w-md mx-auto leading-relaxed">
                    Freshly brewed specialty coffee, pastries, lunch sandwiches & treats delivered right to your desk at ECA.
                </p>
            </div>
            
            <div class="p-6 sm:p-8 space-y-6">
                <!-- Location notice -->
                <div class="flex items-start gap-3 p-3.5 bg-amber-50/80 border border-amber-200/80 rounded-2xl text-xs text-stone-700 shadow-xs">
                    <i class="fas fa-map-marker-alt text-amber-600 text-sm mt-0.5"></i>
                    <div>
                        <strong class="text-amber-900 font-bold">Serving UNECA Compound Offices:</strong>
                        <p class="text-stone-600 mt-0.5">Africa Hall, Secretariat, Congo, Niger, Zambezi, Limpopo, Nile, Conference Center & all agencies.</p>
                    </div>
                </div>

                <!-- Features list -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3.5 bg-stone-50/80 border border-stone-200/80 hover:border-amber-300 hover:bg-amber-50/40 rounded-2xl flex items-center gap-3 transition">
                        <span class="text-2xl">☕</span>
                        <div>
                            <p class="text-sm font-bold text-stone-900">Kaldis Coffee</p>
                            <p class="text-[11px] text-stone-500 font-medium">Macchiato, Latte, Espresso</p>
                        </div>
                    </div>
                    <div class="p-3.5 bg-stone-50/80 border border-stone-200/80 hover:border-amber-300 hover:bg-amber-50/40 rounded-2xl flex items-center gap-3 transition">
                        <span class="text-2xl">🥐</span>
                        <div>
                            <p class="text-sm font-bold text-stone-900">Fresh Pastries</p>
                            <p class="text-[11px] text-stone-500 font-medium">Croissants, Rolls & Muffins</p>
                        </div>
                    </div>
                    <div class="p-3.5 bg-stone-50/80 border border-stone-200/80 hover:border-amber-300 hover:bg-amber-50/40 rounded-2xl flex items-center gap-3 transition">
                        <span class="text-2xl">🥪</span>
                        <div>
                            <p class="text-sm font-bold text-stone-900">Office Lunch</p>
                            <p class="text-[11px] text-stone-500 font-medium">Club Sandwiches & Wraps</p>
                        </div>
                    </div>
                    <div class="p-3.5 bg-stone-50/80 border border-stone-200/80 hover:border-amber-300 hover:bg-amber-50/40 rounded-2xl flex items-center gap-3 transition">
                        <span class="text-2xl">🍰</span>
                        <div>
                            <p class="text-sm font-bold text-stone-900">Cakes & Tortas</p>
                            <p class="text-[11px] text-stone-500 font-medium">Black Forest & Cheesecakes</p>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="space-y-3 pt-2">
                    <a href="miniapp/app.html" 
                       class="flex items-center justify-center gap-2.5 w-full bg-gradient-to-r from-amber-600 via-amber-500 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white py-3.5 rounded-2xl font-extrabold shadow-lg shadow-amber-600/30 hover:shadow-amber-600/40 hover:scale-[1.01] active:scale-[0.99] transition-all duration-200 text-sm tracking-wide">
                        <i class="fas fa-mug-hot text-base animate-bounce"></i> Start Office Order Now
                    </a>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <a href="tel:0992098459" 
                           class="flex items-center justify-center gap-2 w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white py-3 px-3 rounded-2xl font-bold shadow-md shadow-emerald-600/20 hover:shadow-emerald-600/30 hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 text-xs sm:text-sm">
                            <i class="fas fa-phone-alt text-emerald-200 animate-pulse"></i> Mobile: 0992098459
                        </a>
                        
                        <a href="tel:0115444437" 
                           class="flex items-center justify-center gap-2 w-full bg-gradient-to-r from-amber-700 to-orange-700 hover:from-amber-800 hover:to-orange-800 text-white py-3 px-3 rounded-2xl font-bold shadow-md shadow-amber-700/20 hover:shadow-amber-700/30 hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 text-xs sm:text-sm">
                            <i class="fas fa-headset text-amber-200"></i> Ext: 0115444437 (34437)
                        </a>
                    </div>
                </div>
                
                <!-- Footer Info -->
                <div class="pt-4 border-t border-stone-100 text-center text-xs text-stone-500 space-y-1.5">
                    <p>© <?php echo date('Y'); ?> Kaldis Coffee - ECA Branch. All rights reserved.</p>
                    <p class="text-[12px] text-stone-600 font-medium">📍 UNECA Compound, Menelik II Ave, Addis Ababa</p>
                    <p class="text-[12px] text-stone-700 font-semibold">📞 Mobile: <a href="tel:0992098459" class="text-amber-700 font-bold hover:underline">0992098459</a> | ☎ Ext Phone: <a href="tel:0115444437" class="text-amber-700 font-bold hover:underline">0115444437</a> | 🏢 Ext Short: <span class="text-amber-900 font-extrabold bg-amber-100 px-1.5 py-0.5 rounded">34437</span></p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>