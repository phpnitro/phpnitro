#include <string.h>
#include <stdlib.h>

#include <php_embed.h>

/*
 * Grows as needed across a single phpx_embed_eval() call — reset (not
 * freed) at the start of each call, so repeated evals don't leak or
 * reallocate from zero every time. Deliberately global, not
 * threadsafe: see this file's own header docblock on the "one embed
 * SAPI instance for the process" assumption already made throughout
 * this app.
 */
static char *g_output_buffer = NULL;
static size_t g_output_length = 0;
static size_t g_output_capacity = 0;

static size_t phpx_embed_capture_write(const char *str, size_t str_length)
{
    if (str_length == 0) {
        return 0;
    }

    size_t needed = g_output_length + str_length + 1;
    if (needed > g_output_capacity) {
        size_t new_capacity = g_output_capacity == 0 ? 4096 : g_output_capacity;
        while (new_capacity < needed) {
            new_capacity *= 2;
        }
        char *grown = realloc(g_output_buffer, new_capacity);
        if (!grown) {
            /* Out of memory — drop this write rather than crash; the
             * caller gets a truncated result instead of no process at
             * all, same "degrade, don't crash" spirit as every other
             * best-effort capability in this codebase. */
            return 0;
        }
        g_output_buffer = grown;
        g_output_capacity = new_capacity;
    }

    memcpy(g_output_buffer + g_output_length, str, str_length);
    g_output_length += str_length;
    g_output_buffer[g_output_length] = '\0';
    return str_length;
}

void phpx_embed_start(void)
{
    php_embed_module.ub_write = phpx_embed_capture_write;
    php_embed_init(0, NULL);
}

char *phpx_embed_eval(const char *php_code)
{
    if (!php_code) {
        return NULL;
    }

    g_output_length = 0;
    if (g_output_capacity > 0) {
        g_output_buffer[0] = '\0';
    }

    zend_result result = zend_eval_string_ex(php_code, NULL, "phpx_embed_eval", /* handle_exceptions */ true);
    if (result == FAILURE) {
        return NULL;
    }

    if (!g_output_buffer) {
        /* Ran successfully but produced no output at all — an empty
         * string is the correct result here, not NULL (NULL means
         * "failed", not "succeeded with nothing to say"). */
        char *empty = malloc(1);
        if (empty) {
            empty[0] = '\0';
        }
        return empty;
    }

    char *copy = malloc(g_output_length + 1);
    if (!copy) {
        return NULL;
    }
    memcpy(copy, g_output_buffer, g_output_length + 1);
    return copy;
}

void phpx_embed_free_string(char *str)
{
    free(str);
}

void phpx_embed_shutdown(void)
{
    php_embed_shutdown();
    free(g_output_buffer);
    g_output_buffer = NULL;
    g_output_length = 0;
    g_output_capacity = 0;
}
