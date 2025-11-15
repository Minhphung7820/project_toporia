#!/bin/bash
# Script to detect and handle Kafka cluster ID mismatch
# Run this manually if you suspect cluster ID mismatch

KAFKA_DATA_DIR="/var/lib/kafka/data"
MISMATCH_MARKER="$KAFKA_DATA_DIR/.cluster_id_mismatch_detected"

echo "Checking for cluster ID mismatch..."

if docker logs project_topo_kafka 2>&1 | grep -q "InconsistentClusterIdException"; then
  echo "⚠️  Cluster ID mismatch detected in Kafka logs!"
  echo "Creating marker file for auto-reset..."
  docker exec project_topo_kafka touch "$MISMATCH_MARKER" 2>/dev/null || echo "Cannot create marker (container may be stopped)"
  echo "✅ Marker created. Restart Kafka to auto-reset:"
  echo "   docker compose restart kafka"
else
  echo "✅ No cluster ID mismatch detected"
fi

