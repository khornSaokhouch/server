<?php

/**
 * @OA\Info(
 *     title="Laravel API",
 *     version="1.0.0",
 *     description="API documentation for Laravel authentication and user management",
 *     @OA\Contact(
 *         email="you@example.com"
 *     )
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     in="header",
 *     name="Authorization",
 *     description="Use a JWT token to access protected endpoints"
 * )
 */
