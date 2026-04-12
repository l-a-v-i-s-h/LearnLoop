db = db.getSiblingDB("learnloop");

db.createCollection("users", {
	validator: {
		$jsonSchema: {
			bsonType: "object",
			required: ["user_id", "full_name", "username", "email", "password_hash", "created_at"],
			properties: {
				user_id: { bsonType: "string" },
				full_name: { bsonType: "string" },
				username: { bsonType: "string" },
				email: { bsonType: "string" },
				password_hash: { bsonType: "string" },
				created_at: { bsonType: "date" }
			}
		}
	}
});

db.createCollection("study_groups", {
	validator: {
		$jsonSchema: {
			bsonType: "object",
			required: ["group_id", "group_name", "created_by"],
			properties: {
				group_id: { bsonType: "string" },
				group_name: { bsonType: "string" },
				description: { bsonType: "string" },
				created_by: { bsonType: "string" }
			}
		}
	}
});

db.createCollection("group_memberships", {
	validator: {
		$jsonSchema: {
			bsonType: "object",
			required: ["membership_id", "user_id", "group_id", "role"],
			properties: {
				membership_id: { bsonType: "string" },
				user_id: { bsonType: "string" },
				group_id: { bsonType: "string" },
				role: { bsonType: "string" },
				joined_at: { bsonType: "date" }
			}
		}
	}
});

db.createCollection("notes", {
	validator: {
		$jsonSchema: {
			bsonType: "object",
			required: ["note_id", "user_id", "group_id", "file_url"],
			properties: {
				note_id: { bsonType: "string" },
				user_id: { bsonType: "string" },
				group_id: { bsonType: "string" },
				file_url: { bsonType: "string" },
				uploaded_at: { bsonType: "date" }
			}
		}
	}
});

db.createCollection("messages", {
	validator: {
		$jsonSchema: {
			bsonType: "object",
			required: ["message_id", "group_id", "sender_id", "content"],
			properties: {
				message_id: { bsonType: "string" },
				group_id: { bsonType: "string" },
				sender_id: { bsonType: "string" },
				content: { bsonType: "string" },
				timestamp: { bsonType: "date" }
			}
		}
	}
});

db.createCollection("forum_posts", {
	validator: {
		$jsonSchema: {
			bsonType: "object",
			required: ["post_id", "group_id", "user_id", "title", "content"],
			properties: {
				post_id: { bsonType: "string" },
				group_id: { bsonType: "string" },
				user_id: { bsonType: "string" },
				title: { bsonType: "string" },
				content: { bsonType: "string" },
				created_by: { bsonType: "date" }
			}
		}
	}
});

db.createCollection("comments", {
	validator: {
		$jsonSchema: {
			bsonType: "object",
			required: ["comment_id", "post_id", "user_id", "content"],
			properties: {
				comment_id: { bsonType: "string" },
				post_id: { bsonType: "string" },
				user_id: { bsonType: "string" },
				content: { bsonType: "string" },
				created_at: { bsonType: "date" }
			}
		}
	}
});


db.users.createIndex({ user_id: 1 }, { unique: true });
db.users.createIndex({ username: 1 }, { unique: true });
db.users.createIndex({ email: 1 }, { unique: true });
db.users.createIndex({ created_at: -1 });

db.study_groups.createIndex({ group_id: 1 }, { unique: true });
db.study_groups.createIndex({ created_by: 1 });

db.group_memberships.createIndex({ membership_id: 1 }, { unique: true });
db.group_memberships.createIndex({ user_id: 1, group_id: 1 });

db.notes.createIndex({ note_id: 1 }, { unique: true });
db.notes.createIndex({ user_id: 1 });
db.notes.createIndex({ group_id: 1 });

db.messages.createIndex({ message_id: 1 }, { unique: true });
db.messages.createIndex({ group_id: 1 });
db.messages.createIndex({ sender_id: 1 });

db.forum_posts.createIndex({ post_id: 1 }, { unique: true });
db.forum_posts.createIndex({ group_id: 1 });
db.forum_posts.createIndex({ user_id: 1 });

db.comments.createIndex({ comment_id: 1 }, { unique: true });
db.comments.createIndex({ post_id: 1 });
db.comments.createIndex({ user_id: 1 });

print("learnloop database setup complete.");
