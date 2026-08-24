<?php
class Advertisement {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($data) {
        try {
            // Get driver info
            $stmt = $this->pdo->prepare("SELECT name, photo FROM drivers WHERE id = ?");
            $stmt->execute([$data['owner_id']]);
            $driver = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$driver) {
                return ['success' => false, 'error' => 'Driver not found'];
            }
            
            // Prepare car features as JSON
            $car_features = !empty($data['car_features']) ? 
                json_encode(array_map('trim', explode(',', $data['car_features']))) : null;
            
            // Insert advertisement
            $stmt = $this->pdo->prepare("
                INSERT INTO advertisements (
                    owner_id, owner_name, owner_photo, car_model, car_year, car_photo,
                    destination, pickup_location, available_from, available_to,
                    price_per_day, currency, description, car_features, is_active
                ) VALUES (
                    :owner_id, :owner_name, :owner_photo, :car_model, :car_year, :car_photo,
                    :destination, :pickup_location, :available_from, :available_to,
                    :price_per_day, :currency, :description, :car_features, 1
                )
            ");
            
            $result = $stmt->execute([
                ':owner_id' => $data['owner_id'],
                ':owner_name' => $driver['name'],
                ':owner_photo' => $driver['photo'],
                ':car_model' => $data['car_model'],
                ':car_year' => $data['car_year'],
                ':car_photo' => $data['car_photo'] ?? null,
                ':destination' => $data['destination'],
                ':pickup_location' => $data['pickup_location'],
                ':available_from' => $data['available_from'],
                ':available_to' => $data['available_to'],
                ':price_per_day' => $data['price_per_day'],
                ':currency' => $data['currency'] ?? 'GBP',
                ':description' => $data['description'] ?? null,
                ':car_features' => $car_features
            ]);
            
            if ($result) {
                return ['success' => true, 'id' => $this->pdo->lastInsertId()];
            }
            return ['success' => false, 'error' => 'Failed to create advertisement'];
        } catch (PDOException $e) {
            error_log("Advertisement creation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    
    public function update($id, $data) {
        try {
            // Prepare car features as JSON
            $car_features = !empty($data['car_features']) ? 
                json_encode(array_map('trim', explode(',', $data['car_features']))) : null;
            
            $stmt = $this->pdo->prepare("
                UPDATE advertisements SET 
                    car_model = :car_model,
                    car_year = :car_year,
                    car_photo = :car_photo,
                    destination = :destination,
                    pickup_location = :pickup_location,
                    available_from = :available_from,
                    available_to = :available_to,
                    price_per_day = :price_per_day,
                    description = :description,
                    car_features = :car_features
                WHERE id = :id AND owner_id = :owner_id
            ");
            
            $result = $stmt->execute([
                ':car_model' => $data['car_model'],
                ':car_year' => $data['car_year'],
                ':car_photo' => $data['car_photo'] ?? null,
                ':destination' => $data['destination'],
                ':pickup_location' => $data['pickup_location'],
                ':available_from' => $data['available_from'],
                ':available_to' => $data['available_to'],
                ':price_per_day' => $data['price_per_day'],
                ':description' => $data['description'] ?? null,
                ':car_features' => $car_features,
                ':id' => $id,
                ':owner_id' => $data['owner_id']
            ]);
            
            if ($result) {
                return ['success' => true];
            }
            return ['success' => false, 'error' => 'Failed to update advertisement'];
        } catch (PDOException $e) {
            error_log("Advertisement update error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    
    public function getById($id, $owner_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM advertisements 
                WHERE id = ? AND owner_id = ?
            ");
            $stmt->execute([$id, $owner_id]);
            $advertisement = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$advertisement) {
                return ['success' => false, 'error' => 'Advertisement not found'];
            }
            
            // Convert car_features from JSON to array
            if (!empty($advertisement['car_features'])) {
                $features = json_decode($advertisement['car_features'], true);
                $advertisement['car_features'] = implode(', ', $features);
            }
            
            return ['success' => true, 'advertisement' => $advertisement];
        } catch (PDOException $e) {
            error_log("Advertisement fetch error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    
    public function getAllByOwner($owner_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM advertisements 
                WHERE owner_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$owner_id]);
            $advertisements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert car_features from JSON to array for each advertisement
            foreach ($advertisements as &$ad) {
                if (!empty($ad['car_features'])) {
                    $features = json_decode($ad['car_features'], true);
                    $ad['car_features'] = implode(', ', $features);
                }
            }
            
            return ['success' => true, 'advertisements' => $advertisements];
        } catch (PDOException $e) {
            error_log("Advertisements fetch error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    
    public function toggleStatus($id, $owner_id, $is_active) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE advertisements 
                SET is_active = ? 
                WHERE id = ? AND owner_id = ?
            ");
            
            $result = $stmt->execute([$is_active, $id, $owner_id]);
            
            if ($result) {
                return ['success' => true];
            }
            return ['success' => false, 'error' => 'Failed to update status'];
        } catch (PDOException $e) {
            error_log("Advertisement status toggle error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    
    public function delete($id, $owner_id) {
        try {
            $query = "DELETE FROM advertisements WHERE id = ? AND owner_id = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$id, $owner_id]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true];
            }
            return ['success' => false, 'error' => 'Advertisement not found'];
        } catch (PDOException $e) {
            error_log("Advertisement deletion error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    
    public function search($filters = []) {
        try {
            $query = "
                SELECT a.*, 
                       d.name as owner_name,
                       d.photo as owner_photo
                FROM advertisements a
                JOIN drivers d ON a.owner_id = d.id
                WHERE a.is_active = 1
            ";
            
            $params = [];
            
            // Add filters
            if (!empty($filters['pickup_location'])) {
                $query .= " AND a.pickup_location LIKE ?";
                $params[] = '%' . $filters['pickup_location'] . '%';
            }
            
            if (!empty($filters['destination'])) {
                $query .= " AND a.destination LIKE ?";
                $params[] = '%' . $filters['destination'] . '%';
            }
            
            if (!empty($filters['available_from'])) {
                $query .= " AND a.available_from <= ?";
                $params[] = $filters['available_from'];
            }
            
            if (!empty($filters['available_to'])) {
                $query .= " AND a.available_to >= ?";
                $params[] = $filters['available_to'];
            }
            
            if (!empty($filters['max_price'])) {
                $query .= " AND a.price_per_day <= ?";
                $params[] = $filters['max_price'];
            }
            
            if (!empty($filters['car_model'])) {
                $query .= " AND a.car_model LIKE ?";
                $params[] = '%' . $filters['car_model'] . '%';
            }
            
            if (!empty($filters['min_year'])) {
                $query .= " AND a.car_year >= ?";
                $params[] = $filters['min_year'];
            }
            
            $query .= " ORDER BY a.created_at DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $advertisements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert car_features from JSON to array for each advertisement
            foreach ($advertisements as &$ad) {
                if (!empty($ad['car_features'])) {
                    $features = json_decode($ad['car_features'], true);
                    $ad['car_features'] = implode(', ', $features);
                }
            }
            
            return ['success' => true, 'advertisements' => $advertisements];
        } catch (PDOException $e) {
            error_log("Advertisement search error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
} 