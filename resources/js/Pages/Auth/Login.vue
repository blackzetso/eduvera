<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
//import { route } from 'vendor/tightenco/ziggy/src/js';
import { route } from 'ziggy-js';
import { Ziggy } from '@/ziggy';


defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />
    <!-- **************** MAIN CONTENT START **************** -->
    <main>
        <section class="p-0 d-flex align-items-center position-relative overflow-hidden">
            <div class="container-fluid">
                <div class="row">
                    <!-- left -->
                    <div class="col-12 col-lg-6 d-md-flex align-items-center justify-content-center bg-primary bg-opacity-10 vh-lg-100">
                        <div class="p-3 p-lg-5">
                            <!-- Title -->
                            <div class="text-center">
                                <h2 class="fw-bold">Welcome to our largest community</h2>
                                <p class="mb-0 h6 fw-light">Let's learn something new today!</p>
                            </div>
                            <!-- SVG Image -->
                            <img src="/front/theme1/images/element/02.svg" class="mt-5" alt="">
                            <!-- Info -->
                            <div class="d-sm-flex mt-5 align-items-center justify-content-center">
                                <!-- Avatar group -->
                                <ul class="avatar-group mb-2 mb-sm-0">
                                    <li class="avatar avatar-sm">
                                        <img class="avatar-img rounded-circle" src="/front/theme1/images/avatar/01.jpg" alt="avatar">
                                    </li>
                                    <li class="avatar avatar-sm">
                                        <img class="avatar-img rounded-circle" src="/front/theme1/images/avatar/02.jpg" alt="avatar">
                                    </li>
                                    <li class="avatar avatar-sm">
                                        <img class="avatar-img rounded-circle" src="/front/theme1/images/avatar/03.jpg" alt="avatar">
                                    </li>
                                    <li class="avatar avatar-sm">
                                        <img class="avatar-img rounded-circle" src="/front/theme1/images/avatar/04.jpg" alt="avatar">
                                    </li>
                                </ul>
                                <!-- Content -->
                                <p class="mb-0 h6 fw-light ms-0 ms-sm-3">4k+ Students joined us, now it's your turn.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Right -->
                    <div class="col-12 col-lg-6 m-auto">
                        <div class="row my-5">
                            <div class="col-sm-10 col-xl-8 m-auto">
                                <!-- Title -->
                                <span class="mb-0 fs-1">👋</span>
                                <h1 class="fs-2">Login into Eduport!</h1>
                                <p class="lead mb-4">Nice to see you! Please log in with your account.</p>

                                <!-- Form START -->
                                <form @submit.prevent="submit">
                                    <!-- Email -->
                                    <div class="mb-4">
                                        <InputLabel for="email" class="form-label" value="Email address *" />
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text bg-light rounded-start border-0 text-secondary px-3"><i class="bi bi-envelope-fill"></i></span>
                                            <TextInput  id="email" v-model="form.email" type="email" required autofocus class="form-control border-0 bg-light rounded-end ps-1" placeholder="E-mail" />
                                        </div>
                                        <InputError class="mt-2" :message="form.errors.email" />
                                    </div>
                                    <!-- Password -->
                                    <div class="mb-4">
                                        <InputLabel for="password" class="form-label" value="Password *" />
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text bg-light rounded-start border-0 text-secondary px-3"><i class="fas fa-lock"></i></span>
                                            <TextInput id="password" v-model="form.password" type="password" class="form-control border-0 bg-light rounded-end ps-1" required autocomplete="current-password"/>
                                        </div>
                                        <div id="passwordHelpBlock" class="form-text">
                                            Your password must be 8 characters at least
                                        </div>
                                        <InputError class="mt-2" :message="form.errors.password" />
                                    </div>
                                    <!-- Check box -->
                                    <div class="mb-4 d-flex justify-content-between">
                                        <div class="form-check">
                                            <Checkbox v-model:checked="form.remember" class="form-check-input" name="remember" />
                                            <label class="form-check-label" for="exampleCheck1">Remember me</label>
                                        </div>
                                        <div class="text-primary-hover">
                                            <Link v-if="canResetPassword" :href="route('password.request')" class="text-secondary">
                                                Forgot your password?
                                            </Link>
                                        </div>
                                    </div>
                                    <!-- Button -->
                                    <div class="align-items-center mt-0">
                                        <div class="d-grid">
                                            <PrimaryButton class="btn btn-primary mb-0" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                                Log in
                                            </PrimaryButton>
                                        </div>
                                    </div>
                                </form>
                                <!-- Form END -->

                                <!-- Social buttons and divider -->
                                <div class="row">
                                    <!-- Divider with text -->
                                    <div class="position-relative my-4">
                                        <hr>
                                        <p class="small position-absolute top-50 start-50 translate-middle bg-body px-5">Or</p>
                                    </div>
                                </div>

                                <!-- Sign up link -->
                                <div class="mt-4 text-center">
                                    <span>Don't have an account?
                                        <Link :href="route('register')">Signup here</Link>
                                    </span>
                                </div>
                            </div>
                        </div> <!-- Row END -->
                    </div>
                </div> <!-- Row END -->
            </div>
        </section>
    </main>
</template>
