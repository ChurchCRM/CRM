<?php

/* Renderer provides a clean way to output very common HTML blocks with less code.
   It also allows us to modify these HTML blocks without needing to Find/Replace everywhere.
   It writes directly to the output buffer so you can just call it with <? not with <?=

   Example:
     <? $render->Checkbox("Click me!", 'bClicked'); ?>
*/

class Renderer
{
  // Renders a checkbox with the given text, name and value.
  // Note: You do not need to wrap your text in `gettext`. This function will do it for you.
  public function Checkbox($text, $name, $value = 1, $checked = false)
  {
    ?><label class='c-checkbox'>
    <input type="checkbox" Name="<?= $name ?>" value="<?= $value ?>" <?= $checked ? 'checked' : '' ?> />
    <div class='c-indicator'></div>
    <? if (!empty($text)) {
    echo gettext($text);
  } ?>
    </label><?php
  }

  // Renders a radio  button with the given text, name and value.
  // Note: You do not need to wrap your text in `gettext`. This function will do it for you.
  public function Radio($text, $name, $value = 1, $checked = false)
  {
    ?><label class='c-radio'>
    <input type="radio" Name="<?= $name ?>" value="<?= $value ?>" <?= $checked ? 'checked' : '' ?> />
    <div class='c-indicator'></div>
    <? if (!empty($text)) {
    echo gettext($text);
  } ?>
    </label><?php
  }

  // Renders a typical box header element around the given HTML content.
  public function BoxHeader($content, $border = false)
  {
    ?>
    <div class="box-header <?= $border ? 'with-border' : '' ?>">
    <h3 class="box-title"><?= $content ?></h3>
    </div>
    <?php
  }
}

$render = new Renderer();
?>
