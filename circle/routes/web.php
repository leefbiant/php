<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'circle'], function () use ($router){
   
   // 圈子列表
   $router->get('circleList', 'CircleControllers@circleList');
   $router->post('circleList', 'CircleControllers@circleList');

   // 创建圈子
   $router->get('createCircle', 'CircleControllers@CreateCircle');
   $router->post('createCircle', 'CircleControllers@CreateCircle');

   // 编辑圈子
   $router->get('editCircleInfo', 'CircleControllers@EditCircleInfo');
   $router->post('editCircleInfo', 'CircleControllers@EditCircleInfo');

   // 退出圈子
   $router->get('quitCircle', 'CircleControllers@QuitCircle');
   $router->post('quitCircle', 'CircleControllers@QuitCircle');

   // 圈子权限设置
   $router->get('circlePermission', 'CircleControllers@SetCirclePermission');
   $router->post('circlePermission', 'CircleControllers@SetCirclePermission');

   // 圈子成员列表
   $router->get('circleMembers', 'CircleControllers@QueryCircleMembers');
   $router->post('circleMembers', 'CircleControllers@QueryCircleMembers');

   // 用户加入圈子接口
   $router->get('joinCircle', 'CircleControllers@UserJoinCircle');
   $router->post('joinCircle', 'CircleControllers@UserJoinCircle');

   // 用户加入圈子列表接口
   $router->get('joinCircleList', 'CircleControllers@UserJoinCircleList2');
   $router->post('joinCircleList', 'CircleControllers@UserJoinCircleList2');

   // 圈子详情页接口
   $router->get('circleDetail', 'CircleControllers@CircleDetail');
   $router->post('circleDetail', 'CircleControllers@CircleDetail');

   // 设置圈子成员权限
   $router->get('memberPermission', 'CircleControllers@SetCircleUserPermission');
   $router->post('memberPermission', 'CircleControllers@SetCircleUserPermission');

   // 点赞
   $router->get('praise', 'CircleControllers@CircleCotentParise');
   $router->post('praise', 'CircleControllers@CircleCotentParise');

   // 未读消息数
   $router->get('commonInfo', 'CircleControllers@CircleMsgCount');
   $router->post('commonInfo', 'CircleControllers@CircleMsgCount');

   // 个人消息列表
   $router->get('notifyList', 'CircleControllers@CircleMsgList');
   $router->post('notifyList', 'CircleControllers@CircleMsgList');

   ///////////////////////////////////////////////////////////////////
   
    // 查看帖子详情
   $router->get('postDetail', 'CircleControllers@CircleContentDetail');
   $router->post('postDetail', 'CircleControllers@CircleContentDetail');

    // 圈子详情页帖子列表
   $router->get('circleDetailPostList', 'CircleControllers@CircleDetailPostList');
   $router->post('circleDetailPostList', 'CircleControllers@CircleDetailPostList');
   
   // 页帖评论列表
   $router->get('postDetailCommentList', 'CircleControllers@CommentList');
   $router->post('postDetailCommentList', 'CircleControllers@CommentList');

   // 发帖
   $router->get('publishPost', 'CircleControllers@PublishContent');
   $router->post('publishPost', 'CircleControllers@PublishContent');

   // 评论回复
   $router->get('comment', 'CircleControllers@Comment');
   $router->post('comment', 'CircleControllers@Comment');

   // 评论回复
   $router->get('delete', 'CircleControllers@DelContent');
   $router->post('delete', 'CircleControllers@DelContent');

   // 帖子加精华 
   $router->get('postPermission', 'CircleControllers@postPermission');
   $router->post('postPermission', 'CircleControllers@postPermission');

   // 获取项目对应的圈子
   $router->get('associateCircleList', 'CircleControllers@ProjectCircle');
   $router->post('associateCircleList', 'CircleControllers@ProjectCircle');

   // 圈子搜索
   $router->get('searchCircle', 'CircleControllers@searchCircle');
   $router->post('searchCircle', 'CircleControllers@searchCircle');

   // 帖子搜索
   $router->get('searchPost', 'CircleControllers@searchPost');
   $router->post('searchPost', 'CircleControllers@searchPost');

   // 首页搜索
   $router->get('hotCircleSearch', 'CircleControllers@hotCircleSearch');
   $router->post('hotCircleSearch', 'CircleControllers@hotCircleSearch');

   // 设置进圈审核开关
   $router->get('joinCirclePermission', 'CircleControllers@joinCirclePermission');
   $router->post('joinCirclePermission', 'CircleControllers@joinCirclePermission');

   // 审核列表
   $router->get('reviewList', 'CircleControllers@reviewList');
   $router->post('reviewList', 'CircleControllers@reviewList');

   // 审核列表
   $router->get('review', 'CircleControllers@review');
   $router->post('review', 'CircleControllers@review');

   // 获取用户信息
   $router->get('circleUserInfo', 'CircleControllers@circleUserInfo');
   $router->post('circleUserInfo', 'CircleControllers@circleUserInfo');

   $router->get('sortCircleList', 'CircleControllers@sortCircleList');
   $router->post('sortCircleList', 'CircleControllers@sortCircleList');

   $router->get('userPostList', 'CircleControllers@userPostList');
   $router->post('userPostList', 'CircleControllers@userPostList');

   $router->get('createCircleList', 'CircleControllers@createCircleList');
   $router->post('createCircleList', 'CircleControllers@createCircleList');


   // 设置圈子付费
   $router->get('circleFeeSetting', 'CircleControllers@circleFeeSetting');
   $router->post('circleFeeSetting', 'CircleControllers@circleFeeSetting');

   // 下单接口
   $router->get('circleFee', 'CircleControllers@circleFee');
   $router->post('circleFee', 'CircleControllers@circleFee');

   // 支付完通知接口
   $router->get('circleFeeUpdate', 'CircleControllers@circleFeeUpdate');
   $router->post('circleFeeUpdate', 'CircleControllers@circleFeeUpdate');

   // 交易列表
   $router->get('transactionList', 'CircleControllers@transactionList');
   $router->post('transactionList', 'CircleControllers@transactionList');

   // 交易详情
   $router->get('transactionDetails', 'CircleControllers@transactionDetails');
   $router->post('transactionDetails', 'CircleControllers@transactionDetails');

   $router->get('walletInfo', 'CircleControllers@walletInfo');
   $router->post('walletInfo', 'CircleControllers@walletInfo');

   $router->get('withdraw', 'CircleControllers@withdraw');
   $router->post('withdraw', 'CircleControllers@withdraw');

   $router->get('favoritePost', 'CircleControllers@favoritePost');
   $router->post('favoritePost', 'CircleControllers@favoritePost');

   $router->get('favoritePostList', 'CircleControllers@favoritePostList');
   $router->post('favoritePostList', 'CircleControllers@favoritePostList');

   $router->get('rankingList', 'CircleControllers@rankingList');
   $router->post('rankingList', 'CircleControllers@rankingList');

   ///////////////////////////////////////////////////////// for H5 
   $router->get('ServiceGetCircle', 'ServiceCircleControllers@ServiceGetCircle');
   $router->post('ServiceGetCircle', 'ServiceCircleControllers@ServiceGetCircle');

   $router->get('ServicejoinCircle', 'ServiceCircleControllers@ServicejoinCircle');
   $router->post('ServicejoinCircle', 'ServiceCircleControllers@ServicejoinCircle');

   // 圈子推荐
   $router->get('circleRecommend', 'ServiceCircleControllers@HomeRecommend');
   $router->post('circleRecommend', 'ServiceCircleControllers@HomeRecommend');

   // 删除圈子
   $router->get('ServiceDelCircle', 'ServiceCircleControllers@ServiceDelCircle');
   $router->post('ServiceDelCircle', 'ServiceCircleControllers@ServiceDelCircle');


   // 减少圈子机器人
   $router->get('ServiceModifyCircleUser', 'ServiceCircleControllers@ServiceModifyCircleUser');
   $router->post('ServiceModifyCircleUser', 'ServiceCircleControllers@ServiceModifyCircleUser');

   // 活动接口
   // 获取用户创建的圈子
   $router->get('GetUserCreateCircle', 'ServiceCircleControllers@GetUserCreateCircle');
   $router->post('GetUserCreateCircle', 'ServiceCircleControllers@GetUserCreateCircle');

   // 获取人数最多的圈子
   $router->get('ActivityTopCircle', 'ServiceCircleControllers@ActivityTopCircle');
   $router->post('ActivityTopCircle', 'ServiceCircleControllers@ActivityTopCircle');

   // 获取精华帖
   $router->get('GetEssEnce', 'ServiceCircleControllers@GetEssEnce');
   $router->post('GetEssEnce', 'ServiceCircleControllers@GetEssEnce');

   // 获取用户创建人气最高的圈子信息
   $router->get('GetUserTopCircle', 'ServiceCircleControllers@GetUserTopCircle');
   $router->post('GetUserTopCircle', 'ServiceCircleControllers@GetUserTopCircle');

   $router->get('OAPublishNotify', 'ServiceCircleControllers@OAPublishNotify');
   $router->post('OAPublishNotify', 'ServiceCircleControllers@OAPublishNotify');

   $router->get('OACommentNotify', 'ServiceCircleControllers@OACommentNotify');
   $router->post('OACommentNotify', 'ServiceCircleControllers@OACommentNotify');

   $router->get('ServicePayNotify', 'ServiceCircleControllers@ServicePayNotify');
   $router->post('ServicePayNotify', 'ServiceCircleControllers@ServicePayNotify');

   $router->get('ServiceUserWithdraw', 'ServiceCircleControllers@ServiceUserWithdraw');
   $router->post('ServiceUserWithdraw', 'ServiceCircleControllers@ServiceUserWithdraw');

   $router->get('RejectWithdraw', 'ServiceCircleControllers@RejectWithdraw');
   $router->post('RejectWithdraw', 'ServiceCircleControllers@RejectWithdraw');

   $router->get('ServiceH5SharedCircle', 'ServiceCircleControllers@ServiceH5SharedCircle');
   $router->post('ServiceH5SharedCircle', 'ServiceCircleControllers@ServiceH5SharedCircle');

   ///////////////////////////////////////////////////////////////////////////////

   $router->get('EchoTest', 'CircleControllers@EchoTest');
   $router->post('EchoTest', 'CircleControllers@EchoTest');

});
