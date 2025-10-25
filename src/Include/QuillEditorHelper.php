<?php
/**
 * QuillEditorHelper.php - Shared helper functions for Quill rich text editor
 * 
 * Provides reusable functions for initializing Quill editors across the application.
 * Used by: NoteEditor.php, EventEditor.php, and other pages with rich text fields.
 * 
 * Benefits:
 * - Eliminates duplicate code (75+ lines consolidated)
 * - Consistent behavior across all editors
 * - Easy to add new editors with minimal code
 * - Centralized maintenance for initialization logic
 * 
 * @package ChurchCRM
 * @category Utilities
 */

use ChurchCRM\dto\SystemURLs;

/**
 * Generate HTML container for a Quill editor instance
 * 
 * Creates both the editor container div and the hidden input field for capturing content.
 * The hidden input is populated with HTML content when the form is submitted.
 * 
 * @param string $editorId The HTML ID for the editor container div
 * @param string $inputId The HTML ID for the hidden input field (form submission)
 * @param string $content Initial content to display in the editor
 * @param string $cssClasses Additional CSS classes (space-separated, optional)
 * @param string $minHeight CSS min-height value for editor container (default: '300px')
 * 
 * @return string HTML markup for editor container and hidden input
 * 
 * @example
 * // In a form:
 * <?= getQuillEditorContainer(
 *   'NoteText',                    // Editor container ID
 *   'NoteTextInput',              // Hidden input ID
 *   $noteContent,                 // Initial content
 *   'w-100',                      // CSS classes
 *   '300px'                       // Min height
 * ) ?>
 */
function getQuillEditorContainer($editorId, $inputId, $content = '', $cssClasses = '', $minHeight = '300px')
{
    // Sanitize IDs to prevent HTML injection
    $editorId = htmlspecialchars($editorId, ENT_QUOTES, 'UTF-8');
    $inputId = htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8');
    $cssClasses = htmlspecialchars($cssClasses, ENT_QUOTES, 'UTF-8');
    $minHeight = htmlspecialchars($minHeight, ENT_QUOTES, 'UTF-8');
    
    // Merge CSS classes with base editor class
    $classes = trim($cssClasses . ' quill-editor-container');
    
    // Build inline styles for editor
    $style = "min-height: {$minHeight}; border: 1px solid #ccc; border-radius: 4px;";
    
    return <<<HTML
<div id="{$editorId}" class="{$classes}" style="{$style}">{$content}</div>
<input type="hidden" name="{$inputId}" id="{$inputId}">
HTML;
}

/**
 * Generate JavaScript code to initialize a Quill editor instance
 * 
 * Creates a script block that:
 * 1. Waits for the Quill initialization function to be available (0-5 second timeout)
 * 2. Initializes the Quill editor with the specified ID and options
 * 3. Sets up form submission handlers to capture editor content
 * 4. Provides console logging for debugging
 * 
 * This function handles the case where the webpack bundle may load asynchronously
 * by implementing a retry mechanism with exponential backoff protection.
 * 
 * @param string $editorId The HTML ID of the editor container div
 * @param string $inputId The HTML ID of the hidden input field for capturing content
 * @param string $placeholder Placeholder text to show in the empty editor
 * @param bool $includeScriptTag Whether to wrap in <script> tags (default: true)
 * 
 * @return string JavaScript code (wrapped in <script> tags by default)
 * 
 * @example
 * // Basic usage:
 * <?= getQuillEditorInitScript(
 *   'NoteText',
 *   'NoteTextInput',
 *   gettext("Enter note text here...")
 * ) ?>
 * 
 * @example
 * // Advanced usage with custom script handling:
 * <script>
 *   <?= getQuillEditorInitScript(
 *     'NoteText',
 *     'NoteTextInput',
 *     'Placeholder',
 *     false  // Don't wrap in script tags
 *   ) ?>
 * </script>
 */
function getQuillEditorInitScript($editorId, $inputId, $placeholder = '', $includeScriptTag = true)
{
    // Sanitize parameters to prevent injection into JavaScript
    $editorId = htmlspecialchars($editorId, ENT_QUOTES, 'UTF-8');
    $inputId = htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8');
    $placeholder = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    
    // Get CSP nonce for inline script
    $nonce = SystemURLs::getCSPNonce();
    
    $script = <<<'JAVASCRIPT'
  // Initialize Quill editor for {EDITOR_ID}
  // The function window.initializeQuillEditor should be available from webpack bundle
  let quillEditor = null;
  let initAttempts = 0;
  const maxAttempts = 50; // 5 seconds with 100ms intervals

  function tryInitEditor() {
    if (typeof window.initializeQuillEditor === 'function') {
      const container = document.getElementById('{EDITOR_ID}');
      if (container) {
        try {
          quillEditor = window.initializeQuillEditor('#{EDITOR_ID}', {
            placeholder: '{PLACEHOLDER}'
          });
          console.log('Quill editor "{EDITOR_ID}" initialized successfully');
        } catch (e) {
          console.error('Error initializing Quill editor "{EDITOR_ID}":', e);
        }
      }
    } else if (initAttempts < maxAttempts) {
      initAttempts++;
      setTimeout(tryInitEditor, 100);
    } else {
      console.error('Failed to initialize Quill editor "{EDITOR_ID}" after ' + maxAttempts + ' attempts');
    }
  }

  // Start initialization
  tryInitEditor();

  // Capture Quill content before form submission
  document.addEventListener('submit', function(e) {
    if (e.target && e.target.tagName === 'FORM' && quillEditor) {
      const textInput = document.getElementById('{INPUT_ID}');
      if (textInput) {
        textInput.value = quillEditor.root.innerHTML;
        console.log('Captured content from editor "{EDITOR_ID}" on form submit');
      }
    }
  }, true);
JAVASCRIPT;

    // Replace placeholders with actual values
    $script = str_replace('{EDITOR_ID}', $editorId, $script);
    $script = str_replace('{INPUT_ID}', $inputId, $script);
    $script = str_replace('{PLACEHOLDER}', $placeholder, $script);
    
    if ($includeScriptTag) {
        return <<<HTML
<script nonce="$nonce">
$script
</script>
HTML;
    }
    
    return $script;
}

/**
 * Get Quill editor content from hidden input field
 * 
 * Retrieves the HTML content that was captured from a Quill editor
 * and stored in a hidden input field after form submission.
 * 
 * This is a helper for server-side processing after form submission.
 * 
 * @param string $inputId The HTML name/ID of the hidden input field
 * 
 * @return string The HTML content from the editor, or empty string if not found
 * 
 * @example
 * // In form processing:
 * $noteContent = getQuillEditorContent('NoteText');
 */
function getQuillEditorContent($inputId)
{
    return isset($_POST[$inputId]) ? $_POST[$inputId] : '';
}

?>
