<div class="sidebar">
    <ul class="sidebar--items">
        <li>
            <a href="home">
                <span class="icon icon-1"><i class="ri-file-text-line"></i></span>
                <span class="sidebar--item">Take Attendance</span>
            </a>
        </li>
        <li>
            <a href="view-attendance">
                <span class="icon icon-1"><i class="ri-map-pin-line"></i></span>
                <span class="sidebar--item" style="white-space: nowrap;">View Attendance</span>
            </a>
        </li>
        <li>
            <a href="view-students">
                <span class="icon icon-1"><i class="ri-user-line"></i></span>
                <span class="sidebar--item">Students</span>
            </a>
        </li>
       <li>
    <a href="marks">
        <span class="icon icon-1"><i class="ri-file-text-line"></i></span>
        <span class="sidebar--item">Evaluate Marks</span>
    </a>
</li>
<li>
    <a href="manage_units">
        <i class="ri-file-list-3-line"></i>
        <span>Manage Units</span>
    </a>
</li>

        <li>
            <a href="download-record">
                <span class="icon icon-1"><i class="ri-download-line"></i></span>
                <span class="sidebar--item">Download Attendance</span>
            </a>
        </li>
    </ul>

    <ul class="sidebar--bottom-items">
        <li>
            <a href="#">
                <span class="icon icon-2"><i class="ri-settings-3-line"></i></span>
                <span class="sidebar--item">Settings</span>
            </a>
        </li>
        <li>
            <a href="logout">
                <span class="icon icon-2"><i class="ri-logout-box-r-line"></i></span>
                <span class="sidebar--item">Logout</span>
            </a>
        </li>
    </ul>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const currentUrl = window.location.href;
        const links = document.querySelectorAll('.sidebar a');
        links.forEach(function(link) {
            // Normalize URLs to compare only the page name
            const linkPage = link.href.split('/').pop();
            const currentPage = currentUrl.split('/').pop();
            if (linkPage === currentPage) {
                link.id = 'active--link';
            }
        });
    });
</script>
