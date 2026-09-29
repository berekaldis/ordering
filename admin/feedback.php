<?php
require_once '../config.php';
requireAdminLogin();
requirePermission('feedback');

// Get feedback entries
 $page = $_GET['page'] ?? 1;
 $limit = 20;
 $offset = ($page - 1) * $limit;
 $branchFilter = $_GET['branch'] ?? '';
 $ratingFilter = $_GET['rating'] ?? '';
 $dateFrom = $_GET['date_from'] ?? '';
 $dateTo = $_GET['date_to'] ?? '';
 $searchTerm = $_GET['search'] ?? '';
 $chatIdFilter = $_GET['chat_id'] ?? '';

try {
    // Base query with feedback table
    $query = "
        SELECT f.*, b.name as branch_name
        FROM feedback f
        LEFT JOIN branches b ON f.branch_id = b.id
        WHERE 1=1
    ";
    $params = [];
    
    // Add filters
    if ($branchFilter) {
        $query .= " AND f.branch_id = ?";
        $params[] = $branchFilter;
    }
    
    if ($ratingFilter) {
        $query .= " AND (f.delivery_rating = ? OR f.product_rating = ? OR f.service_rating = ?)";
        $params = array_merge($params, [$ratingFilter, $ratingFilter, $ratingFilter]);
    }
    
    if ($dateFrom) {
        $query .= " AND DATE(f.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $query .= " AND DATE(f.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    if ($chatIdFilter) {
        $query .= " AND f.chat_id = ?";
        $params[] = $chatIdFilter;
    }
    
    if ($searchTerm) {
        $query .= " AND (f.written_feedback LIKE ? OR f.complaint_description LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params = array_merge($params, [$searchParam, $searchParam]);
    }
    
    // Add pagination
    $query .= " ORDER BY f.created_at DESC LIMIT $limit OFFSET $offset";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $feedbacks = $stmt->fetchAll();
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) FROM feedback f WHERE 1=1";
    $countParams = [];
    
    if ($branchFilter) {
        $countQuery .= " AND f.branch_id = ?";
        $countParams[] = $branchFilter;
    }
    
    if ($ratingFilter) {
        $countQuery .= " AND (f.delivery_rating = ? OR f.product_rating = ? OR f.service_rating = ?)";
        $countParams = array_merge($countParams, [$ratingFilter, $ratingFilter, $ratingFilter]);
    }
    
    if ($dateFrom) {
        $countQuery .= " AND DATE(f.created_at) >= ?";
        $countParams[] = $dateFrom;
    }
    
    if ($dateTo) {
        $countQuery .= " AND DATE(f.created_at) <= ?";
        $countParams[] = $dateTo;
    }
    
    if ($chatIdFilter) {
        $countQuery .= " AND f.chat_id = ?";
        $countParams[] = $chatIdFilter;
    }
    
    if ($searchTerm) {
        $countQuery .= " AND (f.written_feedback LIKE ? OR f.complaint_description LIKE ?)";
        $searchParam = "%$searchTerm%";
        $countParams = array_merge($countParams, [$searchParam, $searchParam]);
    }
    
    $countStmt = db()->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalFeedbacks = $countStmt->fetchColumn();
    $totalPages = ceil($totalFeedbacks / $limit);
    
    // Get branches for filter
    $branches = db()->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name")->fetchAll();
    
    // Calculate average ratings (only for feedback with ratings)
    $avgStmt = db()->query("
        SELECT 
            AVG(delivery_rating) as avg_delivery,
            AVG(product_rating) as avg_product,
            AVG(service_rating) as avg_service,
            COUNT(*) as total,
            COUNT(DISTINCT chat_id) as unique_customers,
            COUNT(CASE WHEN delivery_rating <= 2 OR product_rating <= 2 OR service_rating <= 2 THEN 1 END) as negative_reviews
        FROM feedback
        WHERE delivery_rating IS NOT NULL OR product_rating IS NOT NULL OR service_rating IS NOT NULL
    ");
    $averages = $avgStmt->fetch();
    
    // Rating distribution
    $distributionStmt = db()->query("
        SELECT 
            rating,
            COUNT(*) as count
        FROM (
            SELECT delivery_rating as rating FROM feedback WHERE delivery_rating IS NOT NULL
            UNION ALL
            SELECT product_rating as rating FROM feedback WHERE product_rating IS NOT NULL
            UNION ALL
            SELECT service_rating as rating FROM feedback WHERE service_rating IS NOT NULL
        ) as ratings
        GROUP BY rating
        ORDER BY rating DESC
    ");
    $distribution = $distributionStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Branch performance (only for feedback with ratings)
    $branchStmt = db()->query("
        SELECT 
            b.name,
            COUNT(*) as feedback_count,
            AVG(f.delivery_rating) as avg_delivery,
            AVG(f.product_rating) as avg_product,
            AVG(f.service_rating) as avg_service
        FROM feedback f
        JOIN branches b ON f.branch_id = b.id
        WHERE f.delivery_rating IS NOT NULL OR f.product_rating IS NOT NULL OR f.service_rating IS NOT NULL
        GROUP BY b.id, b.name
        ORDER BY feedback_count DESC
        LIMIT 5
    ");
    $branchPerformance = $branchStmt->fetchAll();
    
    // Complaint statistics
    $complaintStmt = db()->query("
        SELECT 
            COUNT(*) as total_complaints,
            COUNT(CASE WHEN complaint_status = 'open' THEN 1 END) as open_complaints,
            COUNT(CASE WHEN complaint_status = 'in_progress' THEN 1 END) as in_progress,
            COUNT(CASE WHEN complaint_status = 'resolved' THEN 1 END) as resolved,
            COUNT(CASE WHEN complaint_status = 'closed' THEN 1 END) as closed
        FROM feedback
        WHERE complaint_type IS NOT NULL
    ");
    $complaintStats = $complaintStmt->fetch();
    
} catch (Exception $e) {
    $error = "Failed to load feedback: " . $e->getMessage();
}

function getStarRating($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= $i <= $rating ? '<i class="fas fa-star text-yellow-400"></i>' : '<i class="far fa-star text-gray-300"></i>';
    }
    return $stars;
}

function getRatingColor($rating) {
    if ($rating >= 4.5) return 'text-green-600';
    if ($rating >= 3.5) return 'text-yellow-600';
    if ($rating >= 2.5) return 'text-orange-600';
    return 'text-red-600';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback - Kaldis Coffee ECA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .star-rating { display: inline-flex; gap: 2px; }
        .star-rating i { font-size: 1.2em; }
        .feedback-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .feedback-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .feedback-card.excellent { border-left-color: #10b981; }
        .feedback-card.good { border-left-color: #3b82f6; }
        .feedback-card.average { border-left-color: #f59e0b; }
        .feedback-card.poor { border-left-color: #ef4444; }
        .feedback-card.complaint { border-left-color: #8b5cf6; }
        .chart-container { position: relative; height: 300px; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white shadow-sm border-b px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-800">Customer Feedback</h1>
                <p class="text-sm text-gray-500 mt-1">Monitor customer satisfaction and track complaints</p>
            </div>
            
            <div class="p-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold text-blue-600"><?php echo number_format($averages['total'] ?? 0); ?></p>
                        <p class="text-sm text-gray-500">Total Reviews</p>
                        <p class="text-xs text-gray-400"><?php echo number_format($averages['unique_customers'] ?? 0); ?> customers</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold <?php echo getRatingColor($averages['avg_delivery'] ?? 0); ?>"><?php echo number_format($averages['avg_delivery'] ?? 0, 1); ?></p>
                        <p class="text-sm text-gray-500">Delivery Rating</p>
                        <div class="star-rating justify-center mt-1"><?php echo getStarRating(round($averages['avg_delivery'] ?? 0)); ?></div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold <?php echo getRatingColor($averages['avg_product'] ?? 0); ?>"><?php echo number_format($averages['avg_product'] ?? 0, 1); ?></p>
                        <p class="text-sm text-gray-500">Product Rating</p>
                        <div class="star-rating justify-center mt-1"><?php echo getStarRating(round($averages['avg_product'] ?? 0)); ?></div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold <?php echo getRatingColor($averages['avg_service'] ?? 0); ?>"><?php echo number_format($averages['avg_service'] ?? 0, 1); ?></p>
                        <p class="text-sm text-gray-500">Service Rating</p>
                        <div class="star-rating justify-center mt-1"><?php echo getStarRating(round($averages['avg_service'] ?? 0)); ?></div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold text-red-600"><?php echo number_format($averages['negative_reviews'] ?? 0); ?></p>
                        <p class="text-sm text-gray-500">Negative Reviews</p>
                        <p class="text-xs text-gray-400"><?php echo $averages['total'] ? round(($averages['negative_reviews'] / $averages['total']) * 100, 1) : 0; ?>% of total</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <p class="text-2xl font-bold text-purple-600"><?php echo number_format($complaintStats['total_complaints'] ?? 0); ?></p>
                        <p class="text-sm text-gray-500">Complaints</p>
                        <p class="text-xs text-gray-400"><?php echo $complaintStats['open_complaints'] ?? 0; ?> open</p>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Rating Distribution</h3>
                        <div class="chart-container">
                            <canvas id="ratingChart"></canvas>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Complaint Status</h3>
                        <div class="chart-container">
                            <canvas id="complaintChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <form method="GET" class="flex flex-wrap gap-4 items-center">
                        <div class="flex-1 min-w-[200px]">
                            <select name="branch" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                                <option value="">All Branches</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>" <?php echo $branchFilter == $branch['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($branch['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select name="rating" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                                <option value="">All Ratings</option>
                                <option value="5" <?php echo $ratingFilter == '5' ? 'selected' : ''; ?>>5 Stars</option>
                                <option value="4" <?php echo $ratingFilter == '4' ? 'selected' : ''; ?>>4 Stars</option>
                                <option value="3" <?php echo $ratingFilter == '3' ? 'selected' : ''; ?>>3 Stars</option>
                                <option value="2" <?php echo $ratingFilter == '2' ? 'selected' : ''; ?>>2 Stars</option>
                                <option value="1" <?php echo $ratingFilter == '1' ? 'selected' : ''; ?>>1 Star</option>
                            </select>
                        </div>
                        <div>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" 
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                        </div>
                        <div>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" 
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                <input type="text" name="search" placeholder="Search feedback or complaints..." 
                                       value="<?php echo htmlspecialchars($searchTerm); ?>"
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                            </div>
                        </div>
                        <div>
                            <input type="text" name="chat_id" placeholder="Chat ID" 
                                   value="<?php echo htmlspecialchars($chatIdFilter); ?>"
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                        </div>
                        <div>
                            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                        </div>
                        <div>
                            <a href="feedback.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 inline-block">
                                <i class="fas fa-sync-alt mr-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Feedback List -->
                <div class="space-y-4">
                    <?php if (empty($feedbacks)): ?>
                        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                            <i class="fas fa-comments text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No feedback found matching your filters.</p>
                            <?php if ($_GET): ?>
                                <p class="text-sm text-gray-400 mt-2">Try adjusting your search criteria or clear filters.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($feedbacks as $feedback): 
                            // Determine feedback type and quality
                            $isComplaint = !empty($feedback['complaint_type']);
                            $isRating = !empty($feedback['delivery_rating']) || !empty($feedback['product_rating']) || !empty($feedback['service_rating']);
                            
                            // Determine quality class for ratings
                            $qualityClass = 'complaint';
                            if ($isRating) {
                                $avgRating = ($feedback['delivery_rating'] + $feedback['product_rating'] + $feedback['service_rating']) / 3;
                                if ($avgRating >= 4.5) $qualityClass = 'excellent';
                                elseif ($avgRating >= 3.5) $qualityClass = 'good';
                                elseif ($avgRating >= 2.5) $qualityClass = 'average';
                                else $qualityClass = 'poor';
                            }
                        ?>
                        <div class="feedback-card <?php echo $qualityClass; ?> bg-white rounded-xl shadow-sm overflow-hidden">
                            <div class="p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h3 class="font-semibold text-gray-800">Customer #<?php echo substr($feedback['chat_id'], -4); ?></h3>
                                            <span class="text-xs bg-<?php echo $isComplaint ? 'purple' : ($qualityClass === 'excellent' ? 'green' : ($qualityClass === 'good' ? 'blue' : ($qualityClass === 'average' ? 'yellow' : 'red'))); ?>-100 text-<?php echo $isComplaint ? 'purple' : ($qualityClass === 'excellent' ? 'green' : ($qualityClass === 'good' ? 'blue' : ($qualityClass === 'average' ? 'yellow' : 'red'))); ?>-800 px-2 py-1 rounded-full">
                                                <?php echo $isComplaint ? 'Complaint' : ucfirst($qualityClass); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-store mr-1"></i>Branch: <?php echo htmlspecialchars($feedback['branch_name'] ?? 'N/A'); ?>
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            <i class="fas fa-id-card mr-1"></i>Chat ID: <?php echo htmlspecialchars($feedback['chat_id']); ?>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-clock mr-1"></i><?php echo date('M d, Y g:i A', strtotime($feedback['created_at'])); ?>
                                        </p>
                                    </div>
                                    <button onclick="showFeedbackDetails(<?php echo json_encode($feedback, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)" 
                                            class="text-blue-600 hover:text-blue-800 p-2 rounded-lg hover:bg-blue-50 transition">
                                        <i class="fas fa-expand"></i>
                                    </button>
                                </div>
                                
                                <?php if ($isRating): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                                        <?php if ($feedback['delivery_rating']): ?>
                                            <div class="bg-gray-50 rounded-lg p-3">
                                                <p class="text-sm text-gray-600 mb-1">Delivery Time</p>
                                                <div class="flex items-center justify-between">
                                                    <div class="star-rating"><?php echo getStarRating($feedback['delivery_rating']); ?></div>
                                                    <span class="text-lg font-bold text-gray-800"><?php echo $feedback['delivery_rating']; ?>/5</span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($feedback['product_rating']): ?>
                                            <div class="bg-gray-50 rounded-lg p-3">
                                                <p class="text-sm text-gray-600 mb-1">Product Quality</p>
                                                <div class="flex items-center justify-between">
                                                    <div class="star-rating"><?php echo getStarRating($feedback['product_rating']); ?></div>
                                                    <span class="text-lg font-bold text-gray-800"><?php echo $feedback['product_rating']; ?>/5</span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($feedback['service_rating']): ?>
                                            <div class="bg-gray-50 rounded-lg p-3">
                                                <p class="text-sm text-gray-600 mb-1">Overall Service</p>
                                                <div class="flex items-center justify-between">
                                                    <div class="star-rating"><?php echo getStarRating($feedback['service_rating']); ?></div>
                                                    <span class="text-lg font-bold text-gray-800"><?php echo $feedback['service_rating']; ?>/5</span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($isComplaint): ?>
                                    <div class="bg-purple-50 rounded-lg p-3 mb-3">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i class="fas fa-exclamation-circle text-purple-600"></i>
                                            <span class="font-medium text-purple-800"><?php echo htmlspecialchars($feedback['complaint_type']); ?> Complaint</span>
                                        </div>
                                        <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($feedback['complaint_description'])); ?></p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Status: <span class="font-medium"><?php echo htmlspecialchars($feedback['complaint_status']); ?></span>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($feedback['written_feedback']): ?>
                                    <div class="bg-gray-50 rounded-lg p-3 mt-2">
                                        <p class="text-sm text-gray-600 italic">"<?php echo nl2br(htmlspecialchars($feedback['written_feedback'])); ?>"</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-6 px-6 py-4 border-t flex justify-between items-center bg-gray-50">
                    <div class="text-sm text-gray-500">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalFeedbacks); ?> of <?php echo number_format($totalFeedbacks); ?>
                    </div>
                    <div class="flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&branch=<?php echo urlencode($branchFilter); ?>&rating=<?php echo urlencode($ratingFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&search=<?php echo urlencode($searchTerm); ?>&chat_id=<?php echo urlencode($chatIdFilter); ?>" 
                               class="px-3 py-1 border rounded hover:bg-white text-sm transition">
                                <i class="fas fa-chevron-left mr-1"></i>Prev
                            </a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&branch=<?php echo urlencode($branchFilter); ?>&rating=<?php echo urlencode($ratingFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&search=<?php echo urlencode($searchTerm); ?>&chat_id=<?php echo urlencode($chatIdFilter); ?>" 
                               class="px-3 py-1 border rounded text-sm transition <?php echo $i == $page ? 'bg-green-600 text-white' : 'hover:bg-white'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&branch=<?php echo urlencode($branchFilter); ?>&rating=<?php echo urlencode($ratingFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&search=<?php echo urlencode($searchTerm); ?>&chat_id=<?php echo urlencode($chatIdFilter); ?>" 
                               class="px-3 py-1 border rounded hover:bg-white text-sm transition">
                                Next<i class="fas fa-chevron-right ml-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Feedback Detail Modal -->
    <div id="feedbackModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
            <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-center z-10">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <span class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-comments"></i>
                    </span>
                    <span>Feedback Details</span>
                </h3>
                <button onclick="closeFeedbackModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 w-8 h-8 rounded-lg flex items-center justify-center transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="feedbackContent" class="p-6 overflow-y-auto flex-1">
                <div class="flex justify-center py-12">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showFeedbackDetails(feedback) {
            const modal = document.getElementById('feedbackModal');
            const content = document.getElementById('feedbackContent');
            
            let html = `
            <div class="space-y-6">
                <!-- Header -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm text-gray-500 mb-1">Customer</div>
                            <div class="font-semibold text-gray-800">Customer #${feedback.chat_id.slice(-4)}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">Branch</div>
                            <div class="font-semibold text-gray-800">${escapeHtml(feedback.branch_name || 'N/A')}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">Chat ID</div>
                            <div class="text-sm font-mono text-gray-600">${escapeHtml(feedback.chat_id)}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1">Submitted At</div>
                            <div class="text-sm text-gray-600">${new Date(feedback.created_at).toLocaleString()}</div>
                        </div>
                    </div>
                </div>
                
                <?php if ($feedback['delivery_rating'] || $feedback['product_rating'] || $feedback['service_rating']): ?>
                <!-- Ratings -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3">Ratings</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php if ($feedback['delivery_rating']): ?>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-blue-600 mb-1">${feedback.delivery_rating}/5</div>
                            <div class="star-rating justify-center mb-1">${getStarRating(feedback.delivery_rating)}</div>
                            <div class="text-sm text-gray-600">Delivery Time</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($feedback['product_rating']): ?>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-yellow-600 mb-1">${feedback.product_rating}/5</div>
                            <div class="star-rating justify-center mb-1">${getStarRating(feedback.product_rating)}</div>
                            <div class="text-sm text-gray-600">Product Quality</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($feedback['service_rating']): ?>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-purple-600 mb-1">${feedback.service_rating}/5</div>
                            <div class="star-rating justify-center mb-1">${getStarRating(feedback.service_rating)}</div>
                            <div class="text-sm text-gray-600">Overall Service</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($feedback['complaint_type']): ?>
                <!-- Complaint Details -->
                <div class="bg-purple-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-purple-800 mb-3">Complaint Details</h4>
                    <div class="bg-white p-3 rounded-lg border border-purple-200">
                        <div class="mb-2">
                            <span class="text-sm font-medium text-gray-600">Type:</span>
                            <span class="ml-2 font-semibold text-purple-700">${escapeHtml(feedback.complaint_type)}</span>
                        </div>
                        <div class="mb-2">
                            <span class="text-sm font-medium text-gray-600">Status:</span>
                            <span class="ml-2 px-2 py-1 text-xs rounded-full ${
                                feedback.complaint_status === 'open' ? 'bg-red-100 text-red-800' :
                                feedback.complaint_status === 'in_progress' ? 'bg-yellow-100 text-yellow-800' :
                                feedback.complaint_status === 'resolved' ? 'bg-green-100 text-green-800' :
                                'bg-gray-100 text-gray-800'
                            }">${escapeHtml(feedback.complaint_status)}</span>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600">Description:</span>
                            <p class="mt-1 text-gray-700">${escapeHtml(feedback.complaint_description)}</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($feedback['written_feedback']): ?>
                <!-- Written Feedback -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3">Customer Comments</h4>
                    <div class="bg-white p-3 rounded-lg border">
                        <p class="text-gray-700 italic">"${escapeHtml(feedback.written_feedback)}"</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Actions -->
                <div class="flex gap-3">
                    <button onclick="exportFeedback(${feedback.id})" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-download mr-2"></i>Export
                    </button>
                    <button onclick="searchSimilarFeedback(${feedback.chat_id})" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                        <i class="fas fa-search mr-2"></i>More from this customer
                    </button>
                </div>
            </div>`;
            
            content.innerHTML = html;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        
        function closeFeedbackModal() {
            document.getElementById('feedbackModal').classList.add('hidden');
            document.getElementById('feedbackModal').classList.remove('flex');
        }
        
        function getStarRating(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += i <= rating ? '<i class="fas fa-star text-yellow-400"></i>' : '<i class="far fa-star text-gray-300"></i>';
            }
            return stars;
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        
        function exportFeedback(id) {
            window.open(`export_feedback.php?id=${id}`, '_blank');
        }
        
        function searchSimilarFeedback(chatId) {
            const params = new URLSearchParams(window.location.search);
            params.set('chat_id', chatId);
            window.location.href = `feedback.php?${params.toString()}`;
        }
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Rating Distribution Chart
            const ratingCtx = document.getElementById('ratingChart').getContext('2d');
            new Chart(ratingCtx, {
                type: 'doughnut',
                data: {
                    labels: ['5 Stars', '4 Stars', '3 Stars', '2 Stars', '1 Star'],
                    datasets: [{
                        data: [
                            <?php echo $distribution[5] ?? 0; ?>,
                            <?php echo $distribution[4] ?? 0; ?>,
                            <?php echo $distribution[3] ?? 0; ?>,
                            <?php echo $distribution[2] ?? 0; ?>,
                            <?php echo $distribution[1] ?? 0; ?>
                        ],
                        backgroundColor: [
                            '#10b981',
                            '#3b82f6',
                            '#f59e0b',
                            '#f97316',
                            '#ef4444'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Complaint Status Chart
            const complaintCtx = document.getElementById('complaintChart').getContext('2d');
            new Chart(complaintCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Open', 'In Progress', 'Resolved', 'Closed'],
                    datasets: [{
                        data: [
                            <?php echo $complaintStats['open_complaints'] ?? 0; ?>,
                            <?php echo $complaintStats['in_progress'] ?? 0; ?>,
                            <?php echo $complaintStats['resolved'] ?? 0; ?>,
                            <?php echo $complaintStats['closed'] ?? 0; ?>
                        ],
                        backgroundColor: [
                            '#ef4444',
                            '#f59e0b',
                            '#10b981',
                            '#6b7280'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>