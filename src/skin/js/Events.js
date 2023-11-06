$(function () {
    // jQuery for EventNames.php
    $(".event-recurrence-patterns input[type=radio]").change(function () {
        $el = $(this);
        $container = $el.closest(".row");
        $input = $container
            .find("select, input[type=text]")
            .prop({ disabled: false });
        $container
            .parent()
            .find("select, input[type=text]")
            .not($input)
            .prop({ disabled: true });
    });
});
