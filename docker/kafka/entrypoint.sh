#!/bin/bash
set -e

# Kafka Auto-Reset Entrypoint
# Automatically clears Kafka data if cluster ID mismatch is detected
# This prevents "InconsistentClusterIdException" errors

KAFKA_DATA_DIR="/var/lib/kafka/data"
ZOOKEEPER_CONNECT="${KAFKA_ZOOKEEPER_CONNECT:-zookeeper:2181}"
META_PROPERTIES="$KAFKA_DATA_DIR/meta.properties"
MISMATCH_MARKER="$KAFKA_DATA_DIR/.cluster_id_mismatch_detected"

echo "=== Kafka Auto-Reset Entrypoint ==="
echo "Zookeeper: $ZOOKEEPER_CONNECT"
echo "Kafka Data: $KAFKA_DATA_DIR"

# Wait for Zookeeper to be ready
echo "Waiting for Zookeeper..."
ZOOKEEPER_HOST=$(echo $ZOOKEEPER_CONNECT | cut -d: -f1)
ZOOKEEPER_PORT=$(echo $ZOOKEEPER_CONNECT | cut -d: -f2)

MAX_WAIT=60
WAIT_COUNT=0
while ! nc -z "$ZOOKEEPER_HOST" "$ZOOKEEPER_PORT" 2>/dev/null; do
  if [ $WAIT_COUNT -ge $MAX_WAIT ]; then
    echo "ERROR: Zookeeper not ready after ${MAX_WAIT}s"
    exit 1
  fi
  sleep 1
  WAIT_COUNT=$((WAIT_COUNT + 1))
  if [ $((WAIT_COUNT % 5)) -eq 0 ]; then
    echo "  Waiting... ($WAIT_COUNT/$MAX_WAIT)"
  fi
done
echo "✅ Zookeeper is ready"

# Check for cluster ID mismatch marker from previous run
if [ -f "$MISMATCH_MARKER" ]; then
  echo ""
  echo "⚠️  Cluster ID mismatch marker detected!"
  echo "   Kafka data will be cleared for fresh start..."
  rm -rf "$KAFKA_DATA_DIR"/* 2>/dev/null || true
  rm -f "$MISMATCH_MARKER"
  echo "   ✅ Kafka data cleared"
  echo ""
fi

# Start Kafka using original entrypoint
# If cluster ID mismatch occurs, the restart policy will restart Kafka
# and on next start, the marker will trigger data reset
echo "Starting Kafka..."
exec /etc/confluent/docker/run
