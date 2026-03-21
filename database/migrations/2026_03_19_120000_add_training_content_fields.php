<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('title')->nullable()->after('chatbot_id');
            $table->string('source_type')->default('document')->after('title');
            $table->text('source_url')->nullable()->after('source_type');
            $table->longText('content')->nullable()->after('file_path');
            $table->json('meta_json')->nullable()->after('content');
            $table->index(['tenant_id', 'chatbot_id', 'source_type'], 'documents_tenant_chatbot_source_idx');
        });

        Schema::table('embeddings', function (Blueprint $table) {
            $table->unsignedInteger('chunk_index')->default(0)->after('document_id');
            $table->text('source_url')->nullable()->after('vector_reference');
            $table->longText('content_text')->nullable()->after('source_url');
            $table->json('meta_json')->nullable()->after('content_text');
            $table->index(['tenant_id', 'chatbot_id', 'document_id', 'chunk_index'], 'embeddings_tenant_chatbot_doc_chunk_idx');
        });
    }

    public function down(): void
    {
        Schema::table('embeddings', function (Blueprint $table) {
            $table->dropIndex('embeddings_tenant_chatbot_doc_chunk_idx');
            $table->dropColumn(['chunk_index', 'source_url', 'content_text', 'meta_json']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_tenant_chatbot_source_idx');
            $table->dropColumn(['title', 'source_type', 'source_url', 'content', 'meta_json']);
        });
    }
};
