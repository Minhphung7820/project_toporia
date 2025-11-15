#!/bin/bash
# Script to create all Kafka topics based on config/kafka.php

KAFKA_CONTAINER="project_topo_kafka"
BOOTSTRAP_SERVER="localhost:29092"

echo "=== Creating Kafka Topics ==="
echo ""

# Function to create topic
create_topic() {
    local topic=$1
    local partitions=$2
    local replication_factor=${3:-1}

    echo "Creating topic: $topic (partitions: $partitions, replication: $replication_factor)"
    docker exec $KAFKA_CONTAINER /usr/bin/kafka-topics \
        --bootstrap-server $BOOTSTRAP_SERVER \
        --create \
        --topic "$topic" \
        --partitions "$partitions" \
        --replication-factor "$replication_factor" \
        --if-not-exists \
        2>&1 | grep -v "WARNING" || echo "  ✅ Topic '$topic' ready"
    echo ""
}

# Topics from config/kafka.php
echo "📦 Creating event topics..."
create_topic "orders.events" 10
create_topic "realtime.user" 10
create_topic "realtime.public" 3
create_topic "realtime.presence" 5
create_topic "realtime.chat" 10
create_topic "realtime" 10

echo "✅ All topics created!"
echo ""
echo "📋 Listing all topics:"
docker exec $KAFKA_CONTAINER /usr/bin/kafka-topics \
    --bootstrap-server $BOOTSTRAP_SERVER \
    --list

