#!/bin/bash
# Kafka Wrapper Script
# Monitors Kafka logs for InconsistentClusterIdException and auto-resets

KAFKA_DATA_DIR="/var/lib/kafka/data"
MISMATCH_MARKER="$KAFKA_DATA_DIR/.cluster_id_mismatch_detected"

# Start Kafka in background and monitor logs
/etc/confluent/docker/run 2>&1 | while IFS= read -r line; do
  echo "$line"

  # Check for cluster ID mismatch error
  if echo "$line" | grep -q "InconsistentClusterIdException"; then
    echo "⚠️  DETECTED: Cluster ID mismatch error!"
    echo "Setting marker for auto-reset on next start..."
    touch "$MISMATCH_MARKER"
    echo "Marker file created: $MISMATCH_MARKER"
  fi
done

# Exit with Kafka's exit code
exit ${PIPESTATUS[0]}

