---
name: contacts
description: "Manage a personal contact list stored in memory"
version: "1.0.0"
tools_required: ["memory_read", "memory_write"]
---

# Contacts Skill

## Overview
Allows the agent to store, retrieve, and search personal contacts using the memory store.

## Instructions

### Adding a Contact
1. Use `memory_write` with namespace `contacts`, key as the contact's name (lowercase, hyphenated), and value as a JSON or plain text record.
2. Example: key = `john-doe`, value = `John Doe, email: john@example.com, phone: +39 333 1234567`

### Finding a Contact
1. Use `memory_read` with namespace `contacts` to list all contacts.
2. Filter by name or property.

### Deleting a Contact
1. Use `memory_write` to set the value to an empty string, or use the API.

## Examples

User: "Add John Doe, email john@example.com"
Agent: Stores in memory namespace=contacts, key=john-doe, value="John Doe — email: john@example.com"

User: "Find John"
Agent: Reads all contacts, filters for names containing "John", returns matches.
