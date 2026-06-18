<?php
$client_id = "1322640825707670";
$redirect_uri = "https://www.searchhomesindia.in/superadmin_new/myapicontainer/facebook/callback";
$scope = urlencode("pages_manage_ads,pages_read_engagement,leads_retrieval,ads_management,business_management,public_profile,email,pages_show_list");
// $scope = "pages_manage_ads,pages_read_engagement,leads_retrieval,ads_management,ads_read,pages_show_list,ads_read_custom_audiences,ads_read_lookalike_audiences";
// $scope = "pages_manage_ads,pages_read_engagement,leads_retrieval";
$login_url = "https://www.facebook.com/v15.0/dialog/oauth?client_id=$client_id&redirect_uri=$redirect_uri&scope=$scope&auth_type=rerequest";
header("Location: $login_url");
exit;
?>