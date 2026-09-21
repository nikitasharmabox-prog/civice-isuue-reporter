<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class GeoCluster {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Haversine formula: distance in meters between two lat/lng points
     */
    public static function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    /**
     * Find existing cluster within CLUSTER_RADIUS_METERS of given point and category
     */
    public function findNearbyCluster(float $lat, float $lng, string $category): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM complaint_clusters
             WHERE category = :category
               AND status NOT IN ('resolved')
             ORDER BY last_complaint_at DESC"
        );
        $stmt->execute(['category' => $category]);
        $clusters = $stmt->fetchAll();

        foreach ($clusters as $cluster) {
            $dist = self::haversineDistance($lat, $lng, (float)$cluster['center_lat'], (float)$cluster['center_lng']);
            if ($dist <= CLUSTER_RADIUS_METERS) {
                return $cluster;
            }
        }
        return null;
    }

    /**
     * Create a new cluster
     */
    public function createCluster(float $lat, float $lng, string $category, int $wardId = null): int {
        $stmt = $this->db->prepare(
            "INSERT INTO complaint_clusters (ward_id, category, complaint_count, center_lat, center_lng, radius_meters, severity)
             VALUES (:ward_id, :category, 1, :lat, :lng, :radius, 'low')"
        );
        $stmt->execute([
            'ward_id'  => $wardId,
            'category' => $category,
            'lat'      => $lat,
            'lng'      => $lng,
            'radius'   => CLUSTER_RADIUS_METERS,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Add complaint to existing cluster, update centroid and count
     */
    public function addToCluster(int $clusterId, float $lat, float $lng): array {
        // Get current cluster
        $stmt = $this->db->prepare("SELECT * FROM complaint_clusters WHERE id = :id");
        $stmt->execute(['id' => $clusterId]);
        $cluster = $stmt->fetch();

        $count = $cluster['complaint_count'] + 1;

        // Recalculate weighted centroid
        $newLat = (($cluster['center_lat'] * $cluster['complaint_count']) + $lat) / $count;
        $newLng = (($cluster['center_lng'] * $cluster['complaint_count']) + $lng) / $count;

        // Update severity based on count
        $severity = $this->calculateSeverity($count);

        $stmt = $this->db->prepare(
            "UPDATE complaint_clusters
             SET complaint_count = :count,
                 center_lat = :lat,
                 center_lng = :lng,
                 severity = :severity,
                 last_complaint_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'count'    => $count,
            'lat'      => $newLat,
            'lng'      => $newLng,
            'severity' => $severity,
            'id'       => $clusterId,
        ]);

        return array_merge($cluster, [
            'complaint_count' => $count,
            'center_lat'      => $newLat,
            'center_lng'      => $newLng,
            'severity'        => $severity,
        ]);
    }

    /**
     * Assign a complaint to a cluster (create or join), return cluster id
     */
    public function assignComplaint(int $complaintId, float $lat, float $lng, string $category, int $wardId = null): array {
        $cluster = $this->findNearbyCluster($lat, $lng, $category);

        if ($cluster) {
            $updated = $this->addToCluster($cluster['id'], $lat, $lng);
            $clusterId = $cluster['id'];
            $isNew = false;
            $count = $updated['complaint_count'];
            $severity = $updated['severity'];
        } else {
            $clusterId = $this->createCluster($lat, $lng, $category, $wardId);
            $isNew = true;
            $count = 1;
            $severity = 'low';
        }

        // Link complaint to cluster
        $stmt = $this->db->prepare("UPDATE complaints SET cluster_id = :cid WHERE id = :id");
        $stmt->execute(['cid' => $clusterId, 'id' => $complaintId]);

        return [
            'cluster_id'      => $clusterId,
            'is_new_cluster'  => $isNew,
            'complaint_count' => $count,
            'severity'        => $severity,
            'should_notify'   => $count >= CLUSTER_THRESHOLD,
        ];
    }

    /**
     * Determine severity based on complaint count in cluster
     */
    public function calculateSeverity(int $count): string {
        if ($count >= 10) return 'critical';
        if ($count >= 6)  return 'high';
        if ($count >= 3)  return 'medium';
        return 'low';
    }

    /**
     * Get all clusters with complaint details for map display
     */
    public function getAllClusters(string $status = null): array {
        $where = $status ? "WHERE c.status = :status" : "";
        $params = $status ? ['status' => $status] : [];
        $stmt = $this->db->prepare(
            "SELECT c.*, w.ward_name, w.officer_name, w.officer_email
             FROM complaint_clusters c
             LEFT JOIN wards w ON c.ward_id = w.id
             $where
             ORDER BY c.complaint_count DESC, c.last_complaint_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get complaints belonging to a cluster
     */
    public function getClusterComplaints(int $clusterId): array {
        $stmt = $this->db->prepare(
            "SELECT id, tracking_id, category, description, lat, lng, address,
                    photo_path, status, created_at, citizen_name
             FROM complaints WHERE cluster_id = :cid ORDER BY created_at DESC"
        );
        $stmt->execute(['cid' => $clusterId]);
        return $stmt->fetchAll();
    }

    /**
     * Find ward for a given GPS coordinate
     */
    public function findWardForLocation(float $lat, float $lng): ?array {
        $stmt = $this->db->query("SELECT * FROM wards");
        $wards = $stmt->fetchAll();
        $closest = null;
        $minDist = PHP_FLOAT_MAX;

        foreach ($wards as $ward) {
            if ($ward['lat_center'] && $ward['lng_center']) {
                $dist = self::haversineDistance($lat, $lng, (float)$ward['lat_center'], (float)$ward['lng_center']);
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $closest = $ward;
                }
            }
        }
        return $closest;
    }
}
