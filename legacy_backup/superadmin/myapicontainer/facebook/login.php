<?php
$client_id = "1994857044364536";
$redirect_uri = "https://www.searchhomesindia.in/superadmin/myapicontainer/facebook/callback";
$scope = "pages_manage_ads,pages_read_engagement,leads_retrieval";
// $scope = "pages_manage_ads,pages_read_engagement,leads_retrieval,ads_management,ads_read,pages_show_list,ads_read_custom_audiences,ads_read_lookalike_audiences";
// $scope = "pages_manage_ads,pages_read_engagement,leads_retrieval";
$login_url = "https://www.facebook.com/v15.0/dialog/oauth?client_id=$client_id&redirect_uri=$redirect_uri&scope=$scope&auth_type=rerequest";
header("Location: $login_url");
exit;
?>