db = db.getSiblingDB("learnloop");

db.createCollection("admin", {
	validator: {
		$jsonSchema: {
			bsonType: "object",
			required: ["admin_id", "full_name", "email", "password_hash", "created_at"],
			properties: {
				admin_id: { bsonType: "string" },
				full_name: { bsonType: "string" },
				email: { bsonType: "string" },
				password_hash: { bsonType: "string" },
				created_at: { bsonType: "date" }
			}
		}
	}
});

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

db.admin.createIndex({ admin_id: 1 }, { unique: true });
db.admin.createIndex({ email: 1 }, { unique: true });

db.admin.updateOne(
	{ email: "admin@gmail.com" },
	{
		$setOnInsert: {
			admin_id: "admin-001",
			full_name: "Admin",
			email: "admin@gmail.com",
			password_hash: "$2y$10$XG.AmMAT5mnkP12i9cvoL.j/JKBex4MRA94uKYXLv7wbmi9Vbv6DG",
			created_at: new Date()
		}
	},
	{ upsert: true }
);

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

db.createCollection("notifications", {
	validator: {
		$jsonSchema: {
			bsonType: "object",
			required: ["notification_id", "type", "sender_id", "recipient_id", "status", "created_at"],
			properties: {
				notification_id: { bsonType: "string" },
				type: { bsonType: "string" },
				sender_id: { bsonType: "string" },
				sender_name: { bsonType: "string" },
				sender_email: { bsonType: "string" },
				recipient_id: { bsonType: "string" },
				recipient_email: { bsonType: "string" },
				group_id: { bsonType: "string" },
				group_name: { bsonType: "string" },
				message: { bsonType: "string" },
				status: { bsonType: "string" },
				created_at: { bsonType: "date" },
				updated_at: { bsonType: "date" }
			}
		}
	}
});

db.createCollection("reports", {
	validator: {
		$jsonSchema: {
			bsonType: "object",
			required: ["report_id", "report_type", "message_id", "group_name", "priority", "status", "reporter_id", "reported_user_id", "created_at"],
			properties: {
				report_id: { bsonType: "string" },
				report_type: { bsonType: "string" },
				message_id: { bsonType: "string" },
				group_id: { bsonType: "string" },
				group_name: { bsonType: "string" },
				category: { bsonType: "string" },
				priority: { bsonType: "string" },
				status: { bsonType: "string" },
				reason: { bsonType: "string" },
				details: { bsonType: "string" },
				message_excerpt: { bsonType: "string" },
				evidence_file_name: { bsonType: "string" },
				evidence_file_path: { bsonType: "string" },
				evidence_file_type: { bsonType: "string" },
				reporter_id: { bsonType: "string" },
				reporter_name: { bsonType: "string" },
				reported_user_id: { bsonType: "string" },
				reported_user_name: { bsonType: "string" },
				admin_note: { bsonType: "string" },
				admin_id: { bsonType: "string" },
				admin_name: { bsonType: "string" },
				created_at: { bsonType: "date" },
				updated_at: { bsonType: "date" },
				reviewed_at: { bsonType: ["date", "null"] }
			}
		}
	}
});

db.createCollection("group_members", {
	validator: {
		$jsonSchema: {
			bsonType: "object",
			required: ["member_id", "group_id", "user_id", "role", "joined_at"],
			properties: {
				member_id: { bsonType: "string" },
				group_id: { bsonType: "string" },
				user_id: { bsonType: "string" },
				role: { bsonType: "string" },
				joined_at: { bsonType: "date" }
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

db.notifications.createIndex({ notification_id: 1 }, { unique: true });
db.notifications.createIndex({ recipient_id: 1 });
db.notifications.createIndex({ sender_id: 1 });
db.notifications.createIndex({ recipient_id: 1, status: 1 });

db.reports.createIndex({ report_id: 1 }, { unique: true });
db.reports.createIndex({ status: 1, created_at: -1 });
db.reports.createIndex({ reporter_id: 1, created_at: -1 });
db.reports.createIndex({ reported_user_id: 1, created_at: -1 });

db.group_members.createIndex({ member_id: 1 }, { unique: true });
db.group_members.createIndex({ group_id: 1 });
db.group_members.createIndex({ user_id: 1 });
db.group_members.createIndex({ group_id: 1, user_id: 1 });

print("learnloop database setup complete.");
