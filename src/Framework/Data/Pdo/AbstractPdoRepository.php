<?php
namespace Framework\Data\Pdo;

use Framework\Data\AbstractRepository;
use PDO;
use PDOStatement;
use RuntimeException;

abstract class AbstractPdoRepository extends AbstractRepository
{
    public function __construct(protected PDO $pdo) {}

    protected function begin(): void { $this->pdo->beginTransaction(); }
    protected function commit(): void { $this->pdo->commit(); }
    protected function rollBack(): void { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); }

    protected function stmt(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new RuntimeException('DB statement failed');
        }
        return $stmt;
    }
}
