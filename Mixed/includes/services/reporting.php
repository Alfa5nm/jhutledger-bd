<?php

declare(strict_types=1);

function reportFilters(array $input): array
{
    $from = trim((string) ($input["date_from"] ?? ""));
    $to = trim((string) ($input["date_to"] ?? ""));
    $type = strtoupper(trim((string) ($input["order_type"] ?? "")));
    return [
        "date_from" => validDate($from) ? $from : "",
        "date_to" => validDate($to) ? $to : "",
        "order_type" => in_array($type, ["B2B", "B2C"], true) ? $type : "",
    ];
}

function salesReport(PDO $pdo, ?int $supplierId, array $filters): array
{
    $where = ["o.order_status = 'Completed'"];
    $params = [];
    if ($supplierId !== null) {
        $where[] = "b.supplier_id = ?";
        $params[] = $supplierId;
    }
    if ($filters["date_from"] !== "") {
        $where[] = "DATE(o.order_date) >= ?";
        $params[] = $filters["date_from"];
    }
    if ($filters["date_to"] !== "") {
        $where[] = "DATE(o.order_date) <= ?";
        $params[] = $filters["date_to"];
    }
    if ($filters["order_type"] !== "") {
        $where[] = "o.order_type = ?";
        $params[] = $filters["order_type"];
    }

    $from =
        ' FROM orders o
              JOIN order_item oi ON oi.order_id = o.order_id
              JOIN listing l ON l.listing_id = oi.listing_id
              JOIN textile_batch b ON b.batch_id = l.batch_id
              JOIN users buyer ON buyer.user_id = o.buyer_id
              LEFT JOIN payment p ON p.order_id = o.order_id
              LEFT JOIN stock_transaction returned
                ON returned.order_id = o.order_id AND returned.transaction_type = \'RETURNED\'
              WHERE ' . implode(" AND ", $where);

    $statement = $pdo->prepare(
        'SELECT COUNT(DISTINCT o.order_id) AS order_count,
                COALESCE(SUM(oi.quantity), 0) AS quantity_sold,
                COALESCE(SUM(oi.quantity * oi.selling_price), 0) AS revenue,
                COALESCE(SUM(oi.gross_profit), 0) AS gross_profit,
                COALESCE(SUM(IF(returned.transaction_id IS NULL, 0, oi.quantity * oi.selling_price)), 0) AS returned_revenue,
                COALESCE(SUM(IF(returned.transaction_id IS NULL, 0, oi.gross_profit)), 0) AS returned_profit,
                COALESCE(SUM(IF(returned.transaction_id IS NULL, oi.quantity * oi.selling_price, 0)), 0) AS net_revenue,
                COALESCE(SUM(IF(returned.transaction_id IS NULL, oi.gross_profit, 0)), 0) AS net_profit' . $from,
    );
    $statement->execute($params);
    $summary = $statement->fetch();

    $statement = $pdo->prepare(
        'SELECT b.material_type, COUNT(DISTINCT o.order_id) AS order_count,
                SUM(oi.quantity) AS quantity_sold,
                SUM(oi.quantity * oi.selling_price) AS revenue,
                SUM(oi.gross_profit) AS gross_profit,
                SUM(IF(returned.transaction_id IS NULL, 0, oi.quantity * oi.selling_price)) AS returned_revenue,
                SUM(IF(returned.transaction_id IS NULL, oi.quantity * oi.selling_price, 0)) AS net_revenue' .
            $from .
            '
         GROUP BY b.material_type ORDER BY revenue DESC, b.material_type',
    );
    $statement->execute($params);
    $materials = $statement->fetchAll();

    $statement = $pdo->prepare(
        "SELECT COALESCE(p.payment_status, 'Not submitted') AS payment_status,
                COUNT(DISTINCT o.order_id) AS order_count" .
            $from .
            '
         GROUP BY COALESCE(p.payment_status, \'Not submitted\') ORDER BY payment_status',
    );
    $statement->execute($params);
    $payments = $statement->fetchAll();

    $statement = $pdo->prepare(
        'SELECT o.order_id, o.order_date, o.order_type, buyer.name AS buyer_name,
                b.material_type, oi.quantity, oi.selling_price,
                oi.quantity * oi.selling_price AS revenue, oi.gross_profit,
                COALESCE(p.payment_status, \'Not submitted\') AS payment_status,
                IF(returned.transaction_id IS NULL, 0, 1) AS has_return,
                IF(returned.transaction_id IS NULL, oi.quantity * oi.selling_price, 0) AS net_revenue,
                IF(returned.transaction_id IS NULL, oi.gross_profit, 0) AS net_profit' .
            $from .
            '
         ORDER BY o.order_date DESC, o.order_id DESC',
    );
    $statement->execute($params);

    return [
        "summary" => $summary,
        "materials" => $materials,
        "payments" => $payments,
        "orders" => $statement->fetchAll(),
    ];
}

function findOrderDetails(PDO $pdo, int $orderId): ?array
{
    $statement = $pdo->prepare(
        'SELECT o.*, oi.line_no, oi.listing_id, oi.quantity, oi.selling_price,
                oi.average_cost_snapshot, oi.gross_profit,
                b.batch_id, b.material_type, b.composition, b.color, b.gsm,
                b.`condition`, b.unit_of_measure, b.storage_location, b.supplier_id,
                buyer.name AS buyer_name, buyer.email AS buyer_email, buyer.phone AS buyer_phone,
                buyer.street AS buyer_street, buyer.city AS buyer_city, buyer.district AS buyer_district,
                supplier.name AS supplier_name, supplier.email AS supplier_email, supplier.phone AS supplier_phone,
                q.proposed_price, q.counter_price, q.final_price, q.expiry_date,
                p.payment_id, p.amount AS payment_amount, p.payment_method, p.payment_status, p.payment_date,
                EXISTS(SELECT 1 FROM stock_transaction returned
                       WHERE returned.order_id = o.order_id AND returned.transaction_type = \'RETURNED\') AS has_return
         FROM orders o
         JOIN order_item oi ON oi.order_id = o.order_id AND oi.line_no = 1
         JOIN listing l ON l.listing_id = oi.listing_id
         JOIN textile_batch b ON b.batch_id = l.batch_id
         JOIN users buyer ON buyer.user_id = o.buyer_id
         JOIN users supplier ON supplier.user_id = b.supplier_id
         LEFT JOIN quotation q ON q.quotation_id = o.quotation_id
         LEFT JOIN payment p ON p.order_id = o.order_id
         WHERE o.order_id = ?',
    );
    $statement->execute([$orderId]);
    return $statement->fetch() ?: null;
}

function canViewOrder(array $order, array $user): bool
{
    if ($user["role"] === "admin") {
        return true;
    }
    if ($user["role"] === "supplier") {
        return (int) $order["supplier_id"] === (int) $user["user_id"];
    }
    return in_array($user["role"], ["b2b", "b2c"], true) &&
        (int) $order["buyer_id"] === (int) $user["user_id"] &&
        strtolower((string) $order["order_type"]) === $user["role"];
}

function accessibleOrder(PDO $pdo, int $orderId): array
{
    $order = findOrderDetails($pdo, $orderId);
    $user = currentUser();
    if (!$order || !$user || !canViewOrder($order, $user)) {
        http_response_code(404);
        exit("Order not found.");
    }
    return $order;
}

function stockMovement(string $type, float $quantity): array
{
    return match ($type) {
        "STOCK_ADDED", "RESERVATION_RELEASED", "RETURNED", "ADJUSTMENT_IN" => [
            "class" => "movement-in",
            "symbol" => "+",
            "quantity" => $quantity,
            "effect" => "Stock increased",
        ],
        "RESERVED", "ADJUSTMENT_OUT" => [
            "class" => "movement-out",
            "symbol" => "−",
            "quantity" => $quantity,
            "effect" => "Available stock reduced",
        ],
        "SOLD" => [
            "class" => "movement-neutral",
            "symbol" => "•",
            "quantity" => $quantity,
            "effect" => "Reservation converted to sale",
        ],
        default => [
            "class" => "movement-neutral",
            "symbol" => "•",
            "quantity" => $quantity,
            "effect" => "Ledger record",
        ],
    };
}

function pricingProjection(float $unitCost, float $quantity, float $targetMargin): array
{
    if ($unitCost < 0 || $quantity <= 0) {
        throw new RuntimeException("Cost and quantity must be valid positive values.");
    }
    if ($targetMargin < 0 || $targetMargin >= 100) {
        throw new RuntimeException("Target margin must be at least 0% and below 100%.");
    }
    $suggestedPrice = round($unitCost / (1 - $targetMargin / 100), 2);
    $revenue = round($suggestedPrice * $quantity, 2);
    $cost = round($unitCost * $quantity, 2);
    $profit = round($revenue - $cost, 2);
    return [
        "break_even_price" => round($unitCost, 2),
        "suggested_price" => $suggestedPrice,
        "projected_revenue" => $revenue,
        "projected_cost" => $cost,
        "projected_profit" => $profit,
        "actual_margin" => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
    ];
}

function sustainabilityFilters(array $input): array
{
    $from = trim((string) ($input["date_from"] ?? ""));
    $to = trim((string) ($input["date_to"] ?? ""));
    $channel = strtoupper(trim((string) ($input["channel"] ?? "")));
    $condition = trim((string) ($input["condition"] ?? ""));
    $unit = trim((string) ($input["unit"] ?? ""));
    return [
        "date_from" => validDate($from) ? $from : "",
        "date_to" => validDate($to) ? $to : "",
        "channel" => in_array($channel, ["B2B", "B2C"], true) ? $channel : "",
        "condition" => in_array($condition, ["New", "Surplus", "Dead Stock", "Recycled"], true) ? $condition : "",
        "material" => mb_substr(trim((string) ($input["material"] ?? "")), 0, 100),
        "unit" => in_array($unit, ["kg", "metre", "piece"], true) ? $unit : "",
    ];
}

function sustainabilityReport(PDO $pdo, ?int $supplierId, array $filters): array
{
    $where = ["o.order_status = 'Completed'"];
    $params = [];
    if ($supplierId !== null) {
        $where[] = "b.supplier_id = ?";
        $params[] = $supplierId;
    }
    if ($filters["date_from"] !== "") {
        $where[] = "DATE(o.order_date) >= ?";
        $params[] = $filters["date_from"];
    }
    if ($filters["date_to"] !== "") {
        $where[] = "DATE(o.order_date) <= ?";
        $params[] = $filters["date_to"];
    }
    if ($filters["channel"] !== "") {
        $where[] = "o.order_type = ?";
        $params[] = $filters["channel"];
    }
    if ($filters["condition"] !== "") {
        $where[] = "b.`condition` = ?";
        $params[] = $filters["condition"];
    }
    if ($filters["material"] !== "") {
        $where[] = "b.material_type LIKE ?";
        $params[] = "%" . $filters["material"] . "%";
    }
    if ($filters["unit"] !== "") {
        $where[] = "b.unit_of_measure = ?";
        $params[] = $filters["unit"];
    }

    $from =
        " FROM orders o
              JOIN order_item oi ON oi.order_id = o.order_id
              JOIN listing l ON l.listing_id = oi.listing_id
              JOIN textile_batch b ON b.batch_id = l.batch_id
              LEFT JOIN stock_transaction returned
                ON returned.order_id = o.order_id AND returned.transaction_type = 'RETURNED'
              WHERE " . implode(" AND ", $where);

    $select = "SELECT b.unit_of_measure,
                      SUM(oi.quantity) AS recirculated_quantity,
                      SUM(IF(returned.transaction_id IS NULL, 0, oi.quantity)) AS returned_quantity,
                      SUM(IF(returned.transaction_id IS NULL, oi.quantity, 0)) AS net_quantity,
                      SUM(IF(returned.transaction_id IS NULL, oi.quantity * oi.selling_price, 0)) AS recovered_value,
                      COUNT(DISTINCT o.order_id) AS order_count";
    $statement = $pdo->prepare($select . $from . " GROUP BY b.unit_of_measure ORDER BY b.unit_of_measure");
    $statement->execute($params);
    $units = $statement->fetchAll();
    foreach ($units as &$row) {
        $gross = (float) $row["recirculated_quantity"];
        $row["utilization_percentage"] = $gross > 0 ? round(((float) $row["net_quantity"] / $gross) * 100, 2) : 0;
    }
    unset($row);

    $breakdown = function (string $groupColumn) use ($pdo, $from, $params): array {
        $statement = $pdo->prepare(
            "SELECT {$groupColumn} AS label, b.unit_of_measure,
                    SUM(oi.quantity) AS recirculated_quantity,
                    SUM(IF(returned.transaction_id IS NULL, 0, oi.quantity)) AS returned_quantity,
                    SUM(IF(returned.transaction_id IS NULL, oi.quantity, 0)) AS net_quantity,
                    SUM(IF(returned.transaction_id IS NULL, oi.quantity * oi.selling_price, 0)) AS recovered_value" .
                $from .
                " GROUP BY {$groupColumn}, b.unit_of_measure ORDER BY b.unit_of_measure, net_quantity DESC",
        );
        $statement->execute($params);
        return $statement->fetchAll();
    };

    return [
        "units" => $units,
        "conditions" => $breakdown("b.`condition`"),
        "materials" => $breakdown("b.material_type"),
        "channels" => $breakdown("o.order_type"),
    ];
}

function adminExceptionCounts(PDO $pdo): array
{
    return [
        "Pending payments" => (int) $pdo
            ->query("SELECT COUNT(*) FROM payment WHERE payment_status='Pending'")
            ->fetchColumn(),
        "Overdue confirmed orders" => (int) $pdo
            ->query(
                "SELECT COUNT(*) FROM orders WHERE order_status='Confirmed' AND order_date < NOW() - INTERVAL 2 DAY",
            )
            ->fetchColumn(),
        "Expired open quotations" => (int) $pdo
            ->query("SELECT COUNT(*) FROM quotation WHERE status IN('Pending','Countered') AND expiry_date < CURDATE()")
            ->fetchColumn(),
        "Low-stock batches" => (int) $pdo
            ->query(
                "SELECT COUNT(*) FROM textile_batch WHERE status='Active' AND total_quantity>0 AND available_quantity/total_quantity<=0.20",
            )
            ->fetchColumn(),
        "Zero-quantity active listings" => (int) $pdo
            ->query(
                "SELECT COUNT(*) FROM listing l JOIN textile_batch b ON b.batch_id=l.batch_id WHERE l.status='Active' AND (l.listed_quantity<=0 OR b.available_quantity<=0)",
            )
            ->fetchColumn(),
        "Inactive users with open orders" => (int) $pdo
            ->query(
                "SELECT COUNT(DISTINCT u.user_id) FROM users u JOIN orders o ON o.buyer_id=u.user_id WHERE u.user_status='Inactive' AND o.order_status IN('Pending','Confirmed','Processing')",
            )
            ->fetchColumn(),
    ];
}
