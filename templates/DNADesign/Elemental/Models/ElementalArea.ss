<% if $ElementControllers %>
    <% loop $ElementControllers %>
        <% if $IsElementVisible %>
            $Me
        <% end_if %>
    <% end_loop %>
<% end_if %>
