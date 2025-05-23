<?php require "../includes/header.php"; ?>
<?php require "../config/config.php"; ?>
<?php


if(isset($_SESSION['username'])) {
    header("location:".APPURL."");
}


if(isset($_POST['submit'])) {

    if(empty($_POST['email']) OR empty($_POST['password'])) {
        echo "<script>alert('one or more inputs are empty')</script>";
    } else {
        
        //get the data and do the query that checks the mail

        $email = $_POST['email'];
        $password = $_POST['password']; 

        // Use prepared statements to prevent SQL injection
        $login = $conn->prepare("SELECT * FROM users WHERE email = :email");
        $login->bindParam(':email', $email);
        $login->execute();
        
        $fetch = $login->fetch(PDO::FETCH_ASSOC);

        if($login->rowCount() > 0) {

            if(password_verify($password, $fetch['password_hash'])) { // Use password_hash
                //start sessions

                $_SESSION['username'] = $fetch['username'];
                $_SESSION['email'] = $fetch['email'];
                header("location: ".APPURL."");
                exit;

            } else{
                echo"<script>alert('email or password is wrong')</script>";
  
            }


        } else {
            echo"<script>alert('email or password is wrong')</script>";

        }

       
    }
 }
?>


    <!-- Normal Breadcrumb Begin -->
    <section class="normal-breadcrumb set-bg" data-setbg="<?php echo APPURL; ?>/img/normal-breadcrumb.jpg">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center">
                    <div class="normal__breadcrumb__text">
                        <h2>Login</h2>
                        <p>connectez vous a manga-ascencion</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Normal Breadcrumb End -->

    <!-- Login Section Begin -->
    <section class="login spad">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <div class="login__form">
                        <h3>Login</h3>
                        <form action="login.php" method="POST">
                            <div class="input__item">
                            <input type="text" name="email" placeholder="Email address" required>
                            <span class="icon_mail"></span>
                            </div>
                            <div class="input__item">
                                <input type="password" name="password" placeholder="Password" required>
                                <span class="icon_lock"></span>
                            </div>
                            <button type="submit" name="submit" class="site-btn">Login Now</button>
                        </form>
                        <a href="#" class="forget_pass">Forgot Your Password?</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="login__register">
                        <h3>Dont’t Have An Account?</h3>
                        <a href="signup.php" class="primary-btn">Register Now</a>
                    </div>
                </div>
            </div>
          
        </div>
    </section>
    <!-- Login Section End -->

    <?php require "../includes/footer.php"; ?>